import { Injectable, UnauthorizedException } from '@nestjs/common';
import { DatabaseService } from '../../database/database.service';
import * as bcrypt from 'bcryptjs';
import * as jwt from 'jsonwebtoken';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role_id: number;
  role?: string;
  is_admin?: number;
  store_id?: number | null;
  status?: string;
  avatar?: string;
}

const ROLE_CAPABILITIES: Record<string, string[]> = {
  'quan-tri-vien': ['*'],
  'ban-giam-doc': [
    'kpi.view_company', 'kpi.view_store', 'kpi.export',
    'accounting.view', 'accounting.export',
    'hr.view', 'hr.export',
    'lease.view', 'lease.export', 'lease.ownership_approve',
    'reminder.view', 'gps.view'
  ],
  'ke-toan': [
    'accounting.view', 'accounting.post', 'accounting.reverse',
    'accounting.close_period', 'accounting.reconcile', 'accounting.export',
    'kpi.view_store',
    'lease.view', 'lease.collect', 'lease.reverse_payment',
    'reminder.view'
  ],
  'nhan-su': [
    'hr.view', 'hr.manage_staff', 'hr.manage_attendance', 'hr.manage_schedule', 'hr.export'
  ],
  'quan-ly-cua-hang': [
    'lease.view', 'lease.collect', 'lease.ownership_request',
    'kpi.view_store', 'gps.view', 'reminder.view', 'hr.view'
  ],
  'nhan-vien': [
    'lease.view', 'lease.collect', 'lease.ownership_request',
    'reminder.view'
  ],
};

@Injectable()
export class AuthService {
  private readonly jwtSecret: string;
  private readonly jwtTtlSeconds: number = 86400 * 7; // 7 days

  constructor(private readonly db: DatabaseService) {
    this.jwtSecret = process.env.JWT_SECRET || 'himoto-secret-jwt-key-2b49116';
  }

  getCapabilities(user: AuthUser, roleSlug?: string): string[] {
    if (user.role_id === 1 || user.role === 'admin' || user.is_admin === 1 || roleSlug === 'quan-tri-vien') {
      return ['*'];
    }
    if (roleSlug && ROLE_CAPABILITIES[roleSlug]) {
      return ROLE_CAPABILITIES[roleSlug];
    }
    return ['reminder.view', 'lease.view'];
  }

  async login(email: string, pass: string) {
    const res = await this.db.query(
      'SELECT id, name, email, password, role_id, role, is_admin, store_id, status, avatar FROM users WHERE email = $1 LIMIT 1',
      [email]
    );

    if (res.rows.length === 0) {
      throw new UnauthorizedException('Email hoặc mật khẩu không chính xác');
    }

    const row = res.rows[0];
    if (row.status === 'deactive') {
      throw new UnauthorizedException('Tài khoản đã bị tạm khóa');
    }

    // Compare with bcrypt
    const match = await bcrypt.compare(pass, row.password);
    if (!match) {
      throw new UnauthorizedException('Email hoặc mật khẩu không chính xác');
    }

    // Query role slug if exists
    let roleSlug = '';
    try {
      const roleRes = await this.db.query('SELECT slug FROM roles WHERE id = $1', [row.role_id]);
      if (roleRes.rows.length > 0) {
        roleSlug = roleRes.rows[0].slug;
      }
    } catch {
      // Role table query fallback
    }

    const { password: _, ...userSafe } = row;
    const capabilities = this.getCapabilities(userSafe, roleSlug);

    const token = jwt.sign(
      {
        sub: row.id,
        email: row.email,
        role_id: row.role_id,
        store_id: row.store_id,
      },
      this.jwtSecret,
      { expiresIn: this.jwtTtlSeconds }
    );

    return {
      access_token: token,
      token_type: 'bearer',
      expires_in: this.jwtTtlSeconds,
      user: userSafe,
      capabilities,
    };
  }

  async verifyToken(token: string) {
    try {
      const decoded = jwt.verify(token, this.jwtSecret) as any;
      const res = await this.db.query(
        'SELECT id, name, email, role_id, role, is_admin, store_id, status, avatar FROM users WHERE id = $1 LIMIT 1',
        [decoded.sub]
      );

      if (res.rows.length === 0 || res.rows[0].status === 'deactive') {
        throw new UnauthorizedException('Token không hợp lệ hoặc tài khoản đã bị khóa');
      }

      const userSafe = res.rows[0];
      const capabilities = this.getCapabilities(userSafe);

      const refreshedToken = jwt.sign(
        {
          sub: userSafe.id,
          email: userSafe.email,
          role_id: userSafe.role_id,
          store_id: userSafe.store_id,
        },
        this.jwtSecret,
        { expiresIn: this.jwtTtlSeconds }
      );

      return {
        access_token: refreshedToken,
        token_type: 'bearer',
        expires_in: this.jwtTtlSeconds,
        user: userSafe,
        capabilities,
      };
    } catch (e: any) {
      throw new UnauthorizedException('Token không hợp lệ hoặc đã hết hạn');
    }
  }

  async getUserById(id: number): Promise<AuthUser | null> {
    const res = await this.db.query(
      'SELECT id, name, email, role_id, role, is_admin, store_id, status, avatar FROM users WHERE id = $1 LIMIT 1',
      [id]
    );
    return res.rows[0] || null;
  }
}
