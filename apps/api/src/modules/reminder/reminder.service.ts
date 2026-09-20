import { Injectable } from '@nestjs/common';
import { DatabaseService } from '../../database/database.service';

@Injectable()
export class ReminderService {
  constructor(private readonly db: DatabaseService) {}

  async findAll(params: any = {}) {
    try {
      const res = await this.db.query(
        `SELECT cr.*, c.name as customer_name, c.phone as customer_phone
         FROM customer_reminders cr
         LEFT JOIN customers c ON cr.customer_id = c.id
         ORDER BY cr.id DESC`
      );
      return res.rows;
    } catch {
      return [];
    }
  }
}
