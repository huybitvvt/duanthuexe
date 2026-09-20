import { Injectable } from '@nestjs/common';
import { DatabaseService } from '../../database/database.service';

@Injectable()
export class ExportService {
  constructor(private readonly db: DatabaseService) {}

  async exportVehicles(params: any = {}) {
    const res = await this.db.query('SELECT * FROM vehicles ORDER BY id ASC');
    return this.toCsv(res.rows);
  }

  async exportCustomers(params: any = {}) {
    const res = await this.db.query('SELECT * FROM customers ORDER BY id ASC');
    return this.toCsv(res.rows);
  }

  async exportOrders(params: any = {}) {
    const res = await this.db.query('SELECT * FROM orders ORDER BY id DESC');
    return this.toCsv(res.rows);
  }

  async exportTransactions(params: any = {}) {
    const res = await this.db.query('SELECT * FROM transactions ORDER BY id DESC');
    return this.toCsv(res.rows);
  }

  private toCsv(rows: any[]): string {
    if (!rows || rows.length === 0) return '';
    const headers = Object.keys(rows[0]);
    const csvLines = [headers.join(',')];
    for (const row of rows) {
      const values = headers.map((header) => {
        const val = row[header];
        if (val === null || val === undefined) return '""';
        return `"${String(val).replace(/"/g, '""')}"`;
      });
      csvLines.push(values.join(','));
    }
    return csvLines.join('\n');
  }
}
