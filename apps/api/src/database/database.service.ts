import { Injectable, OnModuleDestroy, OnModuleInit, Logger } from '@nestjs/common';
import { Pool, PoolClient, QueryResult } from 'pg';

@Injectable()
export class DatabaseService implements OnModuleInit, OnModuleDestroy {
  private readonly logger = new Logger(DatabaseService.name);
  private pool: Pool;

  onModuleInit() {
    const connectionString = process.env.DATABASE_URL;
    const ssl = process.env.DB_SSLMODE === 'require' || process.env.NODE_ENV === 'production'
      ? { rejectUnauthorized: false }
      : undefined;

    if (connectionString) {
      this.pool = new Pool({
        connectionString,
        ssl,
        max: 20,
        idleTimeoutMillis: 30000,
        connectionTimeoutMillis: 5000,
      });
    } else {
      this.pool = new Pool({
        host: process.env.DB_HOST || '127.0.0.1',
        port: parseInt(process.env.DB_PORT || '5432', 10),
        database: process.env.DB_DATABASE || 'himoto',
        user: process.env.DB_USERNAME || 'postgres',
        password: process.env.DB_PASSWORD || '',
        ssl,
        max: 20,
        idleTimeoutMillis: 30000,
        connectionTimeoutMillis: 5000,
      });
    }

    const defaultSchema = process.env.DB_SCHEMA || 'himoto';
    this.pool.on('connect', (client: PoolClient) => {
      client.query(`SET search_path TO ${defaultSchema}, public;`).catch((err) => {
        this.logger.error(`Failed to set search_path to ${defaultSchema}`, err.stack);
      });
    });

    this.logger.log(`PostgreSQL connection pool initialized with schema: ${defaultSchema}`);
  }

  async query<T = any>(text: string, params?: any[]): Promise<QueryResult<T>> {
    return this.pool.query<T>(text, params);
  }

  async getClient(): Promise<PoolClient> {
    return this.pool.connect();
  }

  async checkHealth(): Promise<boolean> {
    try {
      const res = await this.pool.query('SELECT 1 as ok');
      return res.rows.length > 0;
    } catch (error) {
      this.logger.error('Database health check failed', error);
      return false;
    }
  }

  async onModuleDestroy() {
    if (this.pool) {
      await this.pool.end();
      this.logger.log('PostgreSQL connection pool closed');
    }
  }
}
