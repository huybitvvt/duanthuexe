import { BadRequestException, Injectable, NotFoundException } from '@nestjs/common';
import { DatabaseService } from '../../database/database.service';
import * as bcrypt from 'bcryptjs';
import { AuthUser } from '../auth/auth.service';

@Injectable()
export class UserService {
  constructor(private readonly db: DatabaseService) {}

  async findAll(params: any = {}, user: AuthUser) {
    const conditions: string[] = ["u.deleted_at IS NULL", "u.status != 'deactive'"];
    const values: any[] = [];
    let idx = 1;

    if (user.role_id !== 1) {
      const role = await this.db.query('SELECT slug FROM roles WHERE id = $1', [user.role_id]);
      if (role.rows[0]?.slug === 'quan-ly-cua-hang' && user.store_id) {
        conditions.push(`u.store_id = $${idx++}`);
        values.push(user.store_id);
      } else {
        conditions.push(`u.id = $${idx++}`);
        values.push(user.id);
      }
    } else if (params.store_id && params.store_id !== 'all') {
      conditions.push(`u.store_id = $${idx++}`);
      values.push(parseInt(params.store_id, 10));
    }

    if (params.search) {
      const q = `%${params.search}%`;
      conditions.push(`(u.name ILIKE $${idx} OR u.email ILIKE $${idx} OR u.phone ILIKE $${idx})`);
      values.push(q);
      idx++;
    }

    const whereClause = conditions.length > 0 ? `WHERE ${conditions.join(' AND ')}` : '';

    const countRes = await this.db.query(
      `SELECT COUNT(*) as total FROM users u ${whereClause}`,
      values
    );
    const total = parseInt(countRes.rows[0]?.total || '0', 10);

    const limit = params.limit ? parseInt(params.limit, 10) : 20;
    const page = params.page ? parseInt(params.page, 10) : 1;
    const offset = (page - 1) * limit;

    const dataRes = await this.db.query(
      `SELECT u.id, u.name, u.email, u.phone, u.role_id, u.role, u.is_admin, u.store_id, u.status, u.created_at, s.store_name
       FROM users u
       LEFT JOIN stores s ON u.store_id = s.id
       ${whereClause}
       ORDER BY u.id DESC
       LIMIT $${idx++} OFFSET $${idx++}`,
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

  async getStaffByStore(storeId: number) {
    const res = await this.db.query(
      `SELECT id, name, email, phone, store_id FROM users WHERE store_id = $1 AND deleted_at IS NULL AND status != 'deactive'`,
      [storeId]
    );
    return res.rows;
  }

  async create(data: any) {
    if (!data.password || typeof data.password !== 'string' || data.password.length < 6) {
      throw new BadRequestException('Mật khẩu cần ít nhất 6 ký tự');
    }
    const hashedPassword = await bcrypt.hash(data.password, 10);
    const res = await this.db.query(
      `INSERT INTO users (name, email, password, phone, role_id, store_id, status, created_at, updated_at)
       VALUES ($1, $2, $3, $4, $5, $6, $7, NOW(), NOW())
       RETURNING id, name, email, phone, role_id, store_id, status`,
      [
        data.name,
        data.email,
        hashedPassword,
        data.phone || '',
        data.role_id || 2,
        data.store_id || 1,
        data.status || 'active',
      ]
    );
    return res.rows[0];
  }

  async update(id: number, data: any) {
    const res = await this.db.query(
      `UPDATE users
       SET name = COALESCE($1, name),
           email = COALESCE($2, email),
           phone = COALESCE($3, phone),
           role_id = COALESCE($4, role_id),
           store_id = COALESCE($5, store_id),
           status = COALESCE($6, status),
           updated_at = NOW()
       WHERE id = $7 AND deleted_at IS NULL
       RETURNING id, name, email, phone, role_id, store_id, status`,
      [
        data.name || null,
        data.email || null,
        data.phone || null,
        data.role_id || null,
        data.store_id || null,
        data.status || null,
        id,
      ]
    );
    if (res.rows.length === 0) {
      throw new NotFoundException(`User #${id} không tồn tại`);
    }
    return res.rows[0];
  }

  async changePassword(id: number, newPass: string) {
    const hashed = await bcrypt.hash(newPass, 10);
    await this.db.query('UPDATE users SET password = $1, updated_at = NOW() WHERE id = $2', [hashed, id]);
    return { success: true, message: 'Đổi mật khẩu thành công' };
  }

  async delete(id: number) {
    const result = await this.db.query(
      `UPDATE users SET status = 'deactive', deleted_at = NOW(), remember_token = NULL, updated_at = NOW()
       WHERE id = $1 AND deleted_at IS NULL RETURNING id`,
      [id],
    );
    if (result.rows.length === 0) {
      throw new NotFoundException(`User #${id} không tồn tại`);
    }
    return { success: true, message: `Đã xóa người dùng #${id}` };
  }
}
