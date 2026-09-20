import { Injectable, NotFoundException } from '@nestjs/common';
import { DatabaseService } from '../../database/database.service';

@Injectable()
export class FinanceService {
  constructor(private readonly db: DatabaseService) {}

  // Banks
  async findAllBanks(params: any = {}) {
    const res = await this.db.query('SELECT * FROM banks ORDER BY id ASC');
    return res.rows;
  }

  // Cash Funds
  async findAllCash(params: any = {}) {
    const res = await this.db.query('SELECT * FROM cash ORDER BY id ASC');
    return res.rows;
  }

  // Transactions
  async findAllTransactions(params: any = {}) {
    const conditions: string[] = [];
    const values: any[] = [];
    let idx = 1;

    if (params.store_id && params.store_id !== 'all') {
      conditions.push(`t.store_id = $${idx++}`);
      values.push(parseInt(params.store_id, 10));
    }

    if (params.type && params.type !== 'all') {
      conditions.push(`t.type = $${idx++}`);
      values.push(params.type);
    }

    if (params.search) {
      const q = `%${params.search}%`;
      conditions.push(`(t.code ILIKE $${idx} OR t.note ILIKE $${idx})`);
      values.push(q);
      idx++;
    }

    const whereClause = conditions.length > 0 ? `WHERE ${conditions.join(' AND ')}` : '';

    const countRes = await this.db.query(
      `SELECT COUNT(*) as total FROM transactions t ${whereClause}`,
      values
    );
    const total = parseInt(countRes.rows[0]?.total || '0', 10);

    const limit = params.limit ? parseInt(params.limit, 10) : 20;
    const page = params.page ? parseInt(params.page, 10) : 1;
    const offset = (page - 1) * limit;

    const dataRes = await this.db.query(
      `SELECT t.*, s.store_name, u.name as user_name, b.bank_name
       FROM transactions t
       LEFT JOIN stores s ON t.store_id = s.id
       LEFT JOIN users u ON t.created_by = u.id
       LEFT JOIN banks b ON t.bank_id = b.id
       ${whereClause}
       ORDER BY t.id DESC
       LIMIT $${idx++} OFFSET $${idx++}`,
      [...values, limit, offset]
    );

    return {
      total,
      per_page: limit,
      current_page: page,
      last_page: Math.ceil(total / limit) || 1,
      data: dataRes.rows,
    };
  }

  async getTransactionStats(params: any = {}) {
    let storeCondition = '';
    const values: any[] = [];
    if (params.store_id && params.store_id !== 'all') {
      storeCondition = 'WHERE store_id = $1';
      values.push(parseInt(params.store_id, 10));
    }

    const res = await this.db.query(
      `SELECT
         COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) as total_in,
         COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) as total_out
       FROM transactions ${storeCondition}`,
      values
    );

    const row = res.rows[0] || {};
    const totalIn = parseFloat(row.total_in || '0');
    const totalOut = parseFloat(row.total_out || '0');

    return {
      total_in: totalIn,
      total_out: totalOut,
      balance: totalIn - totalOut,
    };
  }

  async createTransaction(data: any, userId?: number) {
    const code = `GD-${Date.now().toString().slice(-6)}`;
    const res = await this.db.query(
      `INSERT INTO transactions (code, type, amount, store_id, bank_id, note, order_id, created_by, created_at, updated_at)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, NOW(), NOW())
       RETURNING *`,
      [
        code,
        data.type || 'in',
        data.amount || 0,
        data.store_id || 1,
        data.bank_id || null,
        data.note || '',
        data.order_id || null,
        userId || 1,
      ]
    );
    return res.rows[0];
  }

  // Daily Cash Register
  async findDailyCashRegister(params: any = {}) {
    const conditions: string[] = [];
    const values: any[] = [];
    let idx = 1;

    if (params.store_id && params.store_id !== 'all') {
      conditions.push(`store_id = $${idx++}`);
      values.push(parseInt(params.store_id, 10));
    }

    if (params.date) {
      conditions.push(`date = $${idx++}`);
      values.push(params.date);
    }

    const whereClause = conditions.length > 0 ? `WHERE ${conditions.join(' AND ')}` : '';
    const res = await this.db.query(
      `SELECT * FROM daily_cash_registers ${whereClause} ORDER BY date DESC LIMIT 30`,
      values
    );
    return res.rows;
  }
}
