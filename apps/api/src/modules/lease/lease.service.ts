import { Injectable } from '@nestjs/common';
import { DatabaseService } from '../../database/database.service';

@Injectable()
export class LeaseService {
  constructor(private readonly db: DatabaseService) {}

  async findAll(params: any = {}) {
    try {
      const res = await this.db.query(
        `SELECT lc.*, c.name as customer_name, c.phone as customer_phone, v.name as vehicle_name, v.license
         FROM lease_contracts lc
         LEFT JOIN customers c ON lc.customer_id = c.id
         LEFT JOIN vehicles v ON lc.vehicle_id = v.id
         ORDER BY lc.id DESC`
      );
      return res.rows;
    } catch {
      return [];
    }
  }
}
