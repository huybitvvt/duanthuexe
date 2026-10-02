import { Injectable } from '@nestjs/common';
import { DatabaseService } from '../../database/database.service';

@Injectable()
export class MaintenanceService {
  constructor(private readonly db: DatabaseService) {}

  // Rules
  async findAllRules() {
    const res = await this.db.query('SELECT * FROM maintenance_rules ORDER BY id ASC');
    return res.rows;
  }

  async saveRule(data: any) {
    if (data.id) {
      const res = await this.db.query(
        'UPDATE maintenance_rules SET maintenance_type_id = $1, value = $2, updated_at = NOW() WHERE id = $3 RETURNING *',
        [data.maintenance_type_id, data.value, data.id]
      );
      return res.rows[0];
    }
    const res = await this.db.query(
      'INSERT INTO maintenance_rules (maintenance_type_id, value, created_at, updated_at) VALUES ($1, $2, NOW(), NOW()) RETURNING *',
      [data.maintenance_type_id, data.value]
    );
    return res.rows[0];
  }

  // Logs
  async findAllLogs(params: any = {}) {
    const res = await this.db.query(
      `SELECT ml.*, v.name as vehicle_name, v.license
       FROM maintenance_logs ml
       LEFT JOIN vehicles v ON ml.vehicle_id = v.id
       ORDER BY ml.id DESC
       LIMIT 50`
    );
    return res.rows;
  }

  async saveLog(data: any) {
    const res = await this.db.query(
      `INSERT INTO maintenance_logs (vehicle_id, maintenance_type_id, maintenance_date, note, cost, created_at, updated_at)
       VALUES ($1, $2, $3, $4, $5, NOW(), NOW())
       RETURNING *`,
      [data.vehicle_id, data.maintenance_type_id || 1, data.maintenance_date || new Date().toISOString(), data.note || '', data.cost || 0]
    );
    return res.rows[0];
  }

  // Schedules
  async findAllSchedules(params: any = {}) {
    const res = await this.db.query(
      `SELECT ms.*, v.name as vehicle_name, v.license, s.store_name
       FROM maintenance_schedules ms
       LEFT JOIN vehicles v ON ms.vehicle_id = v.id
       LEFT JOIN stores s ON v.store_id = s.id
       ORDER BY ms.scheduled_date ASC
       LIMIT 50`
    );
    return res.rows;
  }
}
