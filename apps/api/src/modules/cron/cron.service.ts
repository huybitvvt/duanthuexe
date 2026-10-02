import { Injectable, Logger } from '@nestjs/common';
import { Cron } from '@nestjs/schedule';
import { DatabaseService } from '../../database/database.service';

@Injectable()
export class CronService {
  private readonly logger = new Logger(CronService.name);

  constructor(private readonly db: DatabaseService) {}

  @Cron('* * * * *')
  async handleMinuteLateOrders() {
    try {
      // Find orders currently renting ('2') where end_date is in the past
      const res = await this.db.query(
        `SELECT id, code, end_date, total_amount
         FROM orders
         WHERE status = '2' AND end_date < NOW()`
      );

      if (res.rows.length > 0) {
        this.logger.log(`Found ${res.rows.length} overdue orders, updating overdue tracking...`);
        for (const order of res.rows) {
          const endDate = new Date(order.end_date);
          const diffMinutes = Math.floor((Date.now() - endDate.getTime()) / 60000);
          await this.db.query(
            `UPDATE orders
             SET notes = CASE
               WHEN notes LIKE '%[Quá hạn:%' THEN notes
               ELSE COALESCE(notes, '') || ' [Quá hạn: ' || $1 || ' phút]'
             END,
             updated_at = NOW()
             WHERE id = $2`,
            [diffMinutes, order.id]
          );
        }
      }
    } catch (err: any) {
      this.logger.error('Error in handleMinuteLateOrders cron', err?.message);
    }
  }

  @Cron('* * * * *')
  async handleMaintenanceSchedules() {
    try {
      // Periodic check for maintenance alerts
      const res = await this.db.query(
        `SELECT COUNT(*) as count FROM vehicles WHERE status = 'maintenance'`
      );
      // Heartbeat maintenance check
    } catch (err: any) {
      this.logger.error('Error in handleMaintenanceSchedules cron', err?.message);
    }
  }

  @Cron('*/15 * * * *')
  async handleLeadSync() {
    this.logger.log('Executing 15-minute lead sync check');
  }
}
