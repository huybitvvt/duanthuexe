import { Injectable } from '@nestjs/common';
import { DatabaseService } from '../../database/database.service';

@Injectable()
export class PricingService {
  constructor(private readonly db: DatabaseService) {}

  async findAll(params: any = {}) {
    const res = await this.db.query(
      'SELECT * FROM price_vehicles ORDER BY id ASC'
    );
    return res.rows;
  }

  async storeOrUpdate(data: any) {
    if (data.id) {
      const res = await this.db.query(
        `UPDATE price_vehicles
         SET vehicle_type = COALESCE($1, vehicle_type),
             rental_type = COALESCE($2, rental_type),
             price = COALESCE($3, price),
             deposit_price = COALESCE($4, deposit_price),
             updated_at = NOW()
         WHERE id = $5
         RETURNING *`,
        [data.vehicle_type, data.rental_type, data.price, data.deposit_price, data.id]
      );
      return res.rows[0];
    } else {
      const res = await this.db.query(
        `INSERT INTO price_vehicles (vehicle_type, rental_type, price, deposit_price, created_at, updated_at)
         VALUES ($1, $2, $3, $4, NOW(), NOW())
         RETURNING *`,
        [data.vehicle_type, data.rental_type, data.price || 0, data.deposit_price || 0]
      );
      return res.rows[0];
    }
  }

  async delete(id: number) {
    await this.db.query('DELETE FROM price_vehicles WHERE id = $1', [id]);
    return { success: true, message: `Đã xóa bảng giá #${id}` };
  }
}
