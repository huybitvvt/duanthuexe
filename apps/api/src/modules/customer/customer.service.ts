import { Injectable, NotFoundException } from '@nestjs/common';
import { DatabaseService } from '../../database/database.service';

@Injectable()
export class CustomerService {
  constructor(private readonly db: DatabaseService) {}

  async findAll(params: any = {}) {
    const conditions: string[] = [];
    const values: any[] = [];
    let idx = 1;

    if (params.search || params.name || params.phone || params.id_card) {
      const q = `%${params.search || params.name || params.phone || params.id_card}%`;
      conditions.push(`(name ILIKE $${idx} OR phone ILIKE $${idx} OR id_card ILIKE $${idx})`);
      values.push(q);
      idx++;
    }

    const whereClause = conditions.length > 0 ? `WHERE ${conditions.join(' AND ')}` : '';

    const countRes = await this.db.query(
      `SELECT COUNT(*) as total FROM customers ${whereClause}`,
      values
    );
    const total = parseInt(countRes.rows[0]?.total || '0', 10);

    const limit = params.limit ? parseInt(params.limit, 10) : 20;
    const page = params.page ? parseInt(params.page, 10) : 1;
    const offset = (page - 1) * limit;

    const dataRes = await this.db.query(
      `SELECT * FROM customers ${whereClause} ORDER BY id DESC LIMIT $${idx++} OFFSET $${idx++}`,
      [...values, limit, offset]
    );

    return {
      total,
      per_page: limit,
      current_page: page,
      last_page: Math.ceil(total / limit) || 1,
      data: dataRes.rows,
    };
  }

  async findById(id: number) {
    const res = await this.db.query('SELECT * FROM customers WHERE id = $1 LIMIT 1', [id]);
    if (res.rows.length === 0) {
      throw new NotFoundException(`Khách hàng #${id} không tồn tại`);
    }
    return res.rows[0];
  }

  async searchByIdCard(idCard: string) {
    const res = await this.db.query('SELECT * FROM customers WHERE id_card = $1 LIMIT 1', [idCard]);
    return res.rows[0] || null;
  }

  async create(data: any) {
    const res = await this.db.query(
      `INSERT INTO customers (name, phone, email, id_card, address, created_at, updated_at)
       VALUES ($1, $2, $3, $4, $5, NOW(), NOW())
       RETURNING *`,
      [
        data.name,
        data.phone || '',
        data.email || '',
        data.id_card || '',
        data.address || '',
      ]
    );
    return res.rows[0];
  }

  async update(id: number, data: any) {
    const res = await this.db.query(
      `UPDATE customers
       SET name = COALESCE($1, name),
           phone = COALESCE($2, phone),
           email = COALESCE($3, email),
           id_card = COALESCE($4, id_card),
           address = COALESCE($5, address),
           updated_at = NOW()
       WHERE id = $6
       RETURNING *`,
      [
        data.name || null,
        data.phone || null,
        data.email || null,
        data.id_card || null,
        data.address || null,
        id,
      ]
    );
    if (res.rows.length === 0) {
      throw new NotFoundException(`Khách hàng #${id} không tồn tại`);
    }
    return res.rows[0];
  }

  async delete(id: number) {
    await this.db.query('DELETE FROM customers WHERE id = $1', [id]);
    return { success: true, message: `Đã xóa khách hàng #${id}` };
  }
}
