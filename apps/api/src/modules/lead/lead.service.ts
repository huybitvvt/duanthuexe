import { Injectable, NotFoundException } from '@nestjs/common';
import { DatabaseService } from '../../database/database.service';

@Injectable()
export class LeadService {
  constructor(private readonly db: DatabaseService) {}

  async findAll(params: any = {}) {
    const conditions: string[] = [];
    const values: any[] = [];
    let idx = 1;

    if (params.store_id && params.store_id !== 'all') {
      conditions.push(`store_id = $${idx++}`);
      values.push(parseInt(params.store_id, 10));
    }

    if (params.status && params.status !== 'all') {
      conditions.push(`status = $${idx++}`);
      values.push(params.status);
    }

    if (params.search || params.name || params.phone) {
      const q = `%${params.search || params.name || params.phone}%`;
      conditions.push(`(name ILIKE $${idx} OR phone ILIKE $${idx})`);
      values.push(q);
      idx++;
    }

    const whereClause = conditions.length > 0 ? `WHERE ${conditions.join(' AND ')}` : '';

    const countRes = await this.db.query(
      `SELECT COUNT(*) as total FROM leads ${whereClause}`,
      values
    );
    const total = parseInt(countRes.rows[0]?.total || '0', 10);

    const limit = params.limit ? parseInt(params.limit, 10) : 20;
    const page = params.page ? parseInt(params.page, 10) : 1;
    const offset = (page - 1) * limit;

    const dataRes = await this.db.query(
      `SELECT * FROM leads ${whereClause} ORDER BY id DESC LIMIT $${idx++} OFFSET $${idx++}`,
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
    const res = await this.db.query('SELECT * FROM leads WHERE id = $1 LIMIT 1', [id]);
    if (res.rows.length === 0) {
      throw new NotFoundException(`Lead #${id} không tồn tại`);
    }
    return res.rows[0];
  }

  async create(data: any) {
    const res = await this.db.query(
      `INSERT INTO leads (name, phone, email, notes, source, status, store_id, created_at, updated_at)
       VALUES ($1, $2, $3, $4, $5, $6, $7, NOW(), NOW())
       RETURNING *`,
      [
        data.name,
        data.phone,
        data.email || '',
        data.notes || '',
        data.source || 'web',
        data.status || 'new',
        data.store_id || 1,
      ]
    );
    return res.rows[0];
  }

  async update(id: number, data: any) {
    const res = await this.db.query(
      `UPDATE leads
       SET name = COALESCE($1, name),
           phone = COALESCE($2, phone),
           email = COALESCE($3, email),
           notes = COALESCE($4, notes),
           source = COALESCE($5, source),
           status = COALESCE($6, status),
           store_id = COALESCE($7, store_id),
           updated_at = NOW()
       WHERE id = $8
       RETURNING *`,
      [
        data.name || null,
        data.phone || null,
        data.email || null,
        data.notes || null,
        data.source || null,
        data.status || null,
        data.store_id || null,
        id,
      ]
    );
    if (res.rows.length === 0) {
      throw new NotFoundException(`Lead #${id} không tồn tại`);
    }
    return res.rows[0];
  }

  async delete(id: number) {
    await this.db.query('DELETE FROM leads WHERE id = $1', [id]);
    return { success: true, message: `Đã xóa Lead #${id}` };
  }

  async uniqueUsers() {
    const res = await this.db.query('SELECT DISTINCT name, phone FROM leads ORDER BY name ASC');
    return res.rows;
  }
}
