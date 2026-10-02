import { Injectable, NotFoundException } from '@nestjs/common';
import { DatabaseService } from '../../database/database.service';

@Injectable()
export class OrderService {
  constructor(private readonly db: DatabaseService) {}

  async findAll(params: any = {}) {
    const conditions: string[] = [];
    const values: any[] = [];
    let idx = 1;

    if (params.store_id && params.store_id !== 'all') {
      conditions.push(`o.store_id = $${idx++}`);
      values.push(parseInt(params.store_id, 10));
    }

    if (params.status && params.status !== 'all') {
      conditions.push(`o.status = $${idx++}`);
      values.push(params.status.toString());
    }

    if (params.rental_type && params.rental_type !== 'all') {
      conditions.push(`o.rental_type = $${idx++}`);
      values.push(params.rental_type);
    }

    if (params.search) {
      const q = `%${params.search}%`;
      conditions.push(
        `(o.code ILIKE $${idx} OR c.name ILIKE $${idx} OR c.phone ILIKE $${idx} OR v.license ILIKE $${idx})`
      );
      values.push(q);
      idx++;
    }

    const whereClause = conditions.length > 0 ? `WHERE ${conditions.join(' AND ')}` : '';

    const countRes = await this.db.query(
      `SELECT COUNT(DISTINCT o.id) as total
       FROM orders o
       LEFT JOIN customers c ON o.customer_id = c.id
       LEFT JOIN order_items oi ON o.id = oi.order_id
       LEFT JOIN vehicles v ON oi.vehicle_id = v.id
       ${whereClause}`,
      values
    );
    const total = parseInt(countRes.rows[0]?.total || '0', 10);

    const limit = params.limit ? parseInt(params.limit, 10) : 15;
    const page = params.page ? parseInt(params.page, 10) : 1;
    const offset = (page - 1) * limit;

    const dataQuery = `
      SELECT
        o.*,
        c.name as customer_name,
        c.phone as customer_phone,
        c.id_card as customer_id_card,
        s.store_name,
        COALESCE(
          json_agg(
            json_build_object(
              'id', v.id,
              'name', v.name,
              'license', v.license,
              'status', v.status,
              'price_range', v.price_range
            )
          ) FILTER (WHERE v.id IS NOT NULL), '[]'
        ) as vehicles
      FROM orders o
      LEFT JOIN customers c ON o.customer_id = c.id
      LEFT JOIN stores s ON o.store_id = s.id
      LEFT JOIN order_items oi ON o.id = oi.order_id
      LEFT JOIN vehicles v ON oi.vehicle_id = v.id
      ${whereClause}
      GROUP BY o.id, c.id, s.id
      ORDER BY o.id DESC
      LIMIT $${idx++} OFFSET $${idx++}
    `;

    const dataRes = await this.db.query(dataQuery, [...values, limit, offset]);

    return {
      total,
      per_page: limit,
      current_page: page,
      last_page: Math.ceil(total / limit) || 1,
      data: dataRes.rows,
    };
  }

  async findById(id: number) {
    const orderRes = await this.db.query(
      `SELECT
        o.*,
        c.name as customer_name,
        c.phone as customer_phone,
        c.id_card as customer_id_card,
        c.email as customer_email,
        c.address as customer_address,
        s.store_name,
        s.phone as store_phone,
        s.address as store_address,
        u.name as responsible_user_name
      FROM orders o
      LEFT JOIN customers c ON o.customer_id = c.id
      LEFT JOIN stores s ON o.store_id = s.id
      LEFT JOIN users u ON o.created_by = u.id
      WHERE o.id = $1 LIMIT 1`,
      [id]
    );

    if (orderRes.rows.length === 0) {
      throw new NotFoundException(`Hợp đồng thuê #${id} không tồn tại`);
    }

    const order = orderRes.rows[0];

    // Vehicles in order
    const vehiclesRes = await this.db.query(
      `SELECT v.*, oi.price, oi.deposit, oi.start_date, oi.end_date
       FROM order_items oi
       JOIN vehicles v ON oi.vehicle_id = v.id
       WHERE oi.order_id = $1`,
      [id]
    );
    order.vehicles = vehiclesRes.rows;

    // Transactions in order
    const txRes = await this.db.query(
      `SELECT t.*, u.name as user_name, b.bank_name
       FROM transactions t
       LEFT JOIN users u ON t.created_by = u.id
       LEFT JOIN banks b ON t.bank_id = b.id
       WHERE t.order_id = $1
       ORDER BY t.id DESC`,
      [id]
    );
    order.transactions = txRes.rows;

    return order;
  }

  async create(data: any, userId?: number) {
    const code = `HD-${Date.now().toString().slice(-6)}`;
    const client = await this.db.getClient();
    try {
      await client.query('BEGIN');

      // Customer check or create
      let customerId = data.customer_id;
      if (!customerId && data.customer) {
        const custRes = await client.query(
          `INSERT INTO customers (name, phone, id_card, address, created_at, updated_at)
           VALUES ($1, $2, $3, $4, NOW(), NOW())
           RETURNING id`,
          [data.customer.name, data.customer.phone, data.customer.id_card || '', data.customer.address || '']
        );
        customerId = custRes.rows[0]?.id;
      }

      // Order insert
      const orderRes = await client.query(
        `INSERT INTO orders (
           code, customer_id, store_id, rental_type, start_date, end_date,
           total_amount, deposit_amount, status, notes, created_by, created_at, updated_at
         ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, NOW(), NOW())
         RETURNING *`,
        [
          code,
          customerId || 1,
          data.store_id || 1,
          data.rental_type || 'day',
          data.start_date || new Date().toISOString(),
          data.end_date || new Date().toISOString(),
          data.total_amount || 0,
          data.deposit_amount || 0,
          data.status || '1',
          data.notes || '',
          userId || 1,
        ]
      );
      const order = orderRes.rows[0];

      // Vehicle item insert and vehicle status update
      if (data.vehicle_id) {
        await client.query(
          `INSERT INTO order_items (order_id, vehicle_id, price, deposit, start_date, end_date, created_at, updated_at)
           VALUES ($1, $2, $3, $4, $5, $6, NOW(), NOW())`,
          [order.id, data.vehicle_id, data.rental_price || 0, data.deposit_amount || 0, order.start_date, order.end_date]
        );

        await client.query(
          `UPDATE vehicles SET status = 'rent', updated_at = NOW() WHERE id = $1`,
          [data.vehicle_id]
        );
      }

      await client.query('COMMIT');
      return order;
    } catch (e) {
      await client.query('ROLLBACK');
      throw e;
    } finally {
      client.release();
    }
  }

  async update(id: number, data: any) {
    const res = await this.db.query(
      `UPDATE orders
       SET notes = COALESCE($1, notes),
           status = COALESCE($2, status),
           total_amount = COALESCE($3, total_amount),
           deposit_amount = COALESCE($4, deposit_amount),
           updated_at = NOW()
       WHERE id = $5
       RETURNING *`,
      [data.notes || null, data.status || null, data.total_amount || null, data.deposit_amount || null, id]
    );
    return res.rows[0];
  }

  async deposit(id: number, data: any, userId?: number) {
    const res = await this.db.query(
      `UPDATE orders
       SET status = '1',
           deposit_amount = COALESCE($1, deposit_amount),
           updated_at = NOW()
       WHERE id = $2
       RETURNING *`,
      [data.deposit_amount || null, id]
    );
    return res.rows[0];
  }

  async startContract(id: number, data: any, userId?: number) {
    const res = await this.db.query(
      `UPDATE orders
       SET status = '2',
           start_date = NOW(),
           updated_at = NOW()
       WHERE id = $1
       RETURNING *`,
      [id]
    );
    return res.rows[0];
  }

  async complete(id: number, data: any, userId?: number) {
    const client = await this.db.getClient();
    try {
      await client.query('BEGIN');

      const res = await client.query(
        `UPDATE orders
         SET status = '3',
             actual_end_date = NOW(),
             updated_at = NOW()
         WHERE id = $1
         RETURNING *`,
        [id]
      );

      // Return vehicles to ready status
      await client.query(
        `UPDATE vehicles
         SET status = 'ready', updated_at = NOW()
         WHERE id IN (SELECT vehicle_id FROM order_items WHERE order_id = $1)`,
        [id]
      );

      await client.query('COMMIT');
      return res.rows[0];
    } catch (e) {
      await client.query('ROLLBACK');
      throw e;
    } finally {
      client.release();
    }
  }

  async calculateEarlyReturn(data: any) {
    const rentalFeePerDay = data.daily_price || 150000;
    const daysUsed = Math.max(1, data.days_used || 1);
    const totalPaid = data.total_paid || 0;
    const cost = daysUsed * rentalFeePerDay;
    const refund = Math.max(0, totalPaid - cost);

    return {
      days_used: daysUsed,
      total_cost: cost,
      refund_amount: refund,
    };
  }
}
