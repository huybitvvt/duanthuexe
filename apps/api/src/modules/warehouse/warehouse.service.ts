import { Injectable } from '@nestjs/common';
import { DatabaseService } from '../../database/database.service';

@Injectable()
export class WarehouseService {
  constructor(private readonly db: DatabaseService) {}

  async findAll(params: any = {}) {
    const res = await this.db.query(
      `SELECT w.*, s.store_name
       FROM warehouses w
       LEFT JOIN stores s ON w.store_id = s.id
       ORDER BY w.id ASC`
    );
    return res.rows;
  }
}
