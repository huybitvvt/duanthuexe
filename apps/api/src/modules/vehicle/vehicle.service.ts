import { Injectable, NotFoundException } from '@nestjs/common';
import { DatabaseService } from '../../database/database.service';

@Injectable()
export class VehicleService {
  constructor(private readonly db: DatabaseService) {}

  async findAll(params: any = {}) {
    const conditions: string[] = [];
    const values: any[] = [];
    let idx = 1;

    if (params.store_id && params.store_id !== 'all') {
      conditions.push(`v.store_id = $${idx++}`);
      values.push(parseInt(params.store_id, 10));
    }

    if (params.status && params.status !== 'all') {
      conditions.push(`v.status = $${idx++}`);
      values.push(params.status);
    }

    if (params.type && params.type !== 'all') {
      conditions.push(`v.type = $${idx++}`);
      values.push(params.type);
    }

    if (params.search || params.name || params.license) {
      const q = `%${params.search || params.name || params.license}%`;
      conditions.push(`(v.name ILIKE $${idx} OR v.license ILIKE $${idx} OR v.chassis ILIKE $${idx})`);
      values.push(q);
      idx++;
    }

    const whereClause = conditions.length > 0 ? `WHERE ${conditions.join(' AND ')}` : '';

    const countRes = await this.db.query(
      `SELECT COUNT(*) as total FROM vehicles v ${whereClause}`,
      values
    );
    const total = parseInt(countRes.rows[0]?.total || '0', 10);

    const limit = params.limit ? parseInt(params.limit, 10) : 20;
    const page = params.page ? parseInt(params.page, 10) : 1;
    const offset = (page - 1) * limit;

    const dataQuery = `
      SELECT v.*, s.store_name
      FROM vehicles v
      LEFT JOIN stores s ON v.store_id = s.id
      ${whereClause}
      ORDER BY v.id DESC
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

  async getReport(params: any = {}) {
    let storeCondition = '';
    const values: any[] = [];
    if (params.store_id && params.store_id !== 'all') {
      storeCondition = 'WHERE store_id = $1';
      values.push(parseInt(params.store_id, 10));
    }

    const res = await this.db.query(
      `SELECT
         COUNT(*) as total,
         COUNT(CASE WHEN status = 'rent' THEN 1 END) as renting,
         COUNT(CASE WHEN status = 'ready' THEN 1 END) as ready,
         COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance,
         COUNT(CASE WHEN status = 'holding' THEN 1 END) as holding,
         COUNT(CASE WHEN status = 'sold' THEN 1 END) as sold
       FROM vehicles ${storeCondition}`,
      values
    );

    const row = res.rows[0] || {};
    return {
      total: parseInt(row.total || '0', 10),
      renting: parseInt(row.renting || '0', 10),
      ready: parseInt(row.ready || '0', 10),
      maintenance: parseInt(row.maintenance || '0', 10),
      holding: parseInt(row.holding || '0', 10),
      sold: parseInt(row.sold || '0', 10),
    };
  }

  async findById(id: number) {
    const res = await this.db.query(
      `SELECT v.*, s.store_name
       FROM vehicles v
       LEFT JOIN stores s ON v.store_id = s.id
       WHERE v.id = $1 LIMIT 1`,
      [id]
    );
    if (res.rows.length === 0) {
      throw new NotFoundException(`Xe #${id} không tồn tại`);
    }
    return res.rows[0];
  }

  async create(data: any, userId?: number) {
    const res = await this.db.query(
      `INSERT INTO vehicles (
         name, brand, type, year, store_id, license, chassis, engine,
         status, cost_price, price_range, price_min, price_max,
         type_of_service_id, created_by, created_at, updated_at
       ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, NOW(), NOW())
       RETURNING *`,
      [
        data.name,
        data.brand || 'Honda',
        data.type || 'Scooter',
        data.year || '2024',
        data.store_id || 1,
        data.license,
        data.chassis || '',
        data.engine || '',
        data.status || 'ready',
        data.cost_price || 0,
        data.price_range || 150000,
        data.price_min || 0,
        data.price_max || 0,
        data.type_of_service_id || 1,
        userId || 1,
      ]
    );
    return res.rows[0];
  }

  async update(id: number, data: any) {
    const res = await this.db.query(
      `UPDATE vehicles
       SET name = COALESCE($1, name),
           brand = COALESCE($2, brand),
           type = COALESCE($3, type),
           year = COALESCE($4, year),
           store_id = COALESCE($5, store_id),
           license = COALESCE($6, license),
           chassis = COALESCE($7, chassis),
           engine = COALESCE($8, engine),
           status = COALESCE($9, status),
           cost_price = COALESCE($10, cost_price),
           price_range = COALESCE($11, price_range),
           updated_at = NOW()
       WHERE id = $12
       RETURNING *`,
      [
        data.name || null,
        data.brand || null,
        data.type || null,
        data.year || null,
        data.store_id || null,
        data.license || null,
        data.chassis || null,
        data.engine || null,
        data.status || null,
        data.cost_price || null,
        data.price_range || null,
        id,
      ]
    );
    if (res.rows.length === 0) {
      throw new NotFoundException(`Xe #${id} không tồn tại`);
    }
    return res.rows[0];
  }

  async delete(id: number) {
    await this.db.query('DELETE FROM vehicles WHERE id = $1', [id]);
    return { success: true, message: `Đã xóa xe #${id}` };
  }
}
