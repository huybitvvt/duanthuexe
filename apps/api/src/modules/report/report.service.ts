import { Injectable } from '@nestjs/common';
import { DatabaseService } from '../../database/database.service';

@Injectable()
export class ReportService {
  constructor(private readonly db: DatabaseService) {}

  async getQuickReport(params: any = {}) {
    const res = await this.db.query(
      `SELECT
         COUNT(*) as total_orders,
         COALESCE(SUM(total_amount), 0) as total_revenue
       FROM orders`
    );
    return res.rows[0];
  }

  async getDetailReport(params: any = {}) {
    let storeCond = '';
    const values: any[] = [];
    if (params.store_id && params.store_id !== 'all') {
      storeCond = 'WHERE o.store_id = $1';
      values.push(parseInt(params.store_id, 10));
    }

    const res = await this.db.query(
      `SELECT
         o.id, o.code, o.rental_type, o.start_date, o.end_date, o.total_amount, o.deposit_amount, o.status,
         c.name as customer_name, c.phone as customer_phone,
         s.store_name
       FROM orders o
       LEFT JOIN customers c ON o.customer_id = c.id
       LEFT JOIN stores s ON o.store_id = s.id
       ${storeCond}
       ORDER BY o.id DESC
       LIMIT 50`,
      values
    );
    return res.rows;
  }

  async getDetailReportDayByDay(params: any = {}) {
    return this.getDetailReport(params);
  }

  async getKpiReport(params: any = {}) {
    return {
      monthly_revenue_target: 250000000,
      monthly_revenue_actual: 215000000,
      fleet_utilization_rate: 88.5,
      retention_rate: 92.0,
      new_customers_count: 142,
    };
  }
}
