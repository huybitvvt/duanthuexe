import { Injectable, NotFoundException } from '@nestjs/common';
import { DatabaseService } from '../../database/database.service';

@Injectable()
export class StoreService {
  constructor(private readonly db: DatabaseService) {}

  async findAll() {
    const res = await this.db.query(
      'SELECT id, store_name, address, phone, email, status, created_at, updated_at FROM stores ORDER BY id ASC'
    );
    return res.rows;
  }

  async findById(id: number) {
    const res = await this.db.query(
      'SELECT id, store_name, address, phone, email, status, created_at, updated_at FROM stores WHERE id = $1 LIMIT 1',
      [id]
    );
    if (res.rows.length === 0) {
      throw new NotFoundException(`Cửa hàng #${id} không tồn tại`);
    }
    return res.rows[0];
  }

  async create(data: any) {
    const res = await this.db.query(
      `INSERT INTO stores (store_name, address, phone, email, status, created_at, updated_at)
       VALUES ($1, $2, $3, $4, $5, NOW(), NOW())
       RETURNING *`,
      [
        data.store_name || data.name,
        data.address || '',
        data.phone || '',
        data.email || '',
        data.status || 'active',
      ]
    );
    return res.rows[0];
  }

  async update(id: number, data: any) {
    const res = await this.db.query(
      `UPDATE stores
       SET store_name = COALESCE($1, store_name),
           address = COALESCE($2, address),
           phone = COALESCE($3, phone),
           email = COALESCE($4, email),
           status = COALESCE($5, status),
           updated_at = NOW()
       WHERE id = $6
       RETURNING *`,
      [
        data.store_name || data.name || null,
        data.address || null,
        data.phone || null,
        data.email || null,
        data.status || null,
        id,
      ]
    );
    if (res.rows.length === 0) {
      throw new NotFoundException(`Cửa hàng #${id} không tồn tại`);
    }
    return res.rows[0];
  }

  async delete(id: number) {
    await this.db.query('DELETE FROM stores WHERE id = $1', [id]);
    return { success: true, message: `Đã xóa cửa hàng #${id}` };
  }
}
