import { Injectable } from '@nestjs/common';
import { DatabaseService } from '../../database/database.service';

@Injectable()
export class DashboardService {
  constructor(private readonly db: DatabaseService) {}

  async getOverview(params: any = {}) {
    let storeCond = '';
    const values: any[] = [];
    if (params.store_id && params.store_id !== 'all') {
      storeCond = 'WHERE store_id = $1';
      values.push(parseInt(params.store_id, 10));
    }

    const vehicleStats = await this.db.query(
      `SELECT
         COUNT(*) as total,
         COUNT(CASE WHEN status = 'rent' THEN 1 END) as renting,
         COUNT(CASE WHEN status = 'ready' THEN 1 END) as ready,
         COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance
       FROM vehicles ${storeCond}`,
      values
    );

    const orderStats = await this.db.query(
      `SELECT
         COUNT(*) as total_orders,
         COALESCE(SUM(total_amount), 0) as total_revenue
       FROM orders ${storeCond}`,
      values
    );

    return {
      vehicles: vehicleStats.rows[0],
      orders: orderStats.rows[0],
    };
  }

  async getReport(params: any = {}) {
    return this.getOverview(params);
  }

  async getReportChart(params: any = {}) {
    return {
      labels: ['Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6'],
      data: [35000000, 42000000, 58000000, 51000000, 67000000, 75000000],
    };
  }
}
