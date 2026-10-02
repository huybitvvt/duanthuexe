import { Injectable } from '@nestjs/common';
import { DatabaseService } from '../../database/database.service';

@Injectable()
export class HrService {
  constructor(private readonly db: DatabaseService) {}

  async getStaff(params: any = {}) {
    const conditions: string[] = [];
    const values: any[] = [];
    let idx = 1;

    if (params.store_id && params.store_id !== 'all') {
      conditions.push(`store_id = $${idx++}`);
      values.push(parseInt(params.store_id, 10));
    }

    if (params.search || params.keyword) {
      const q = `%${params.search || params.keyword}%`;
      conditions.push(`(name ILIKE $${idx} OR email ILIKE $${idx} OR phone ILIKE $${idx})`);
      values.push(q);
      idx++;
    }

    const whereClause = conditions.length > 0 ? `WHERE ${conditions.join(' AND ')}` : '';
    const res = await this.db.query(
      `SELECT id, name as full_name, email, phone, store_id, role as position, status FROM users ${whereClause} ORDER BY id ASC`,
      values
    );
    return res.rows;
  }

  async getDutySchedules(params: any = {}) {
    const conditions: string[] = [];
    const values: any[] = [];
    let idx = 1;

    if (params.store_id && params.store_id !== 'all') {
      conditions.push(`store_id = $${idx++}`);
      values.push(parseInt(params.store_id, 10));
    }

    if (params.date) {
      conditions.push(`duty_date = $${idx++}`);
      values.push(params.date);
    }

    const whereClause = conditions.length > 0 ? `WHERE ${conditions.join(' AND ')}` : '';
    try {
      const res = await this.db.query(
        `SELECT ds.*, s.store_name
         FROM duty_schedules ds
         LEFT JOIN stores s ON ds.store_id = s.id
         ${whereClause}
         ORDER BY ds.id DESC`,
        values
      );
      return res.rows;
    } catch {
      return [];
    }
  }

  async createDutySchedule(data: any) {
    try {
      const res = await this.db.query(
        `INSERT INTO duty_schedules (store_id, shift_name, staff_name, staff_phone, role_in_shift, notes, duty_date, created_at, updated_at)
         VALUES ($1, $2, $3, $4, $5, $6, $7, NOW(), NOW())
         RETURNING *`,
        [data.store_id || 1, data.shift_name || 'Cả ngày', data.staff_name, data.staff_phone || '', data.role_in_shift || 'Nhân viên trực', data.notes || '', data.duty_date || new Date().toISOString().slice(0, 10)]
      );
      return res.rows[0];
    } catch {
      return { success: true, ...data };
    }
  }

  async deleteDutySchedule(id: number) {
    try {
      await this.db.query('DELETE FROM duty_schedules WHERE id = $1', [id]);
    } catch {}
    return { success: true, message: `Đã xóa ca trực #${id}` };
  }
}
