import {
  CanActivate,
  ExecutionContext,
  Injectable,
  UnauthorizedException,
} from '@nestjs/common';
import { AuthService } from './auth.service';

@Injectable()
export class JwtAuthGuard implements CanActivate {
  constructor(private readonly authService: AuthService) {}

  async canActivate(context: ExecutionContext): Promise<boolean> {
    const request = context.switchToHttp().getRequest();
    const authHeader = request.headers.authorization;

    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      throw new UnauthorizedException('Chưa cung cấp token xác thực (Bearer token required)');
    }

    const token = authHeader.substring(7).trim();
    try {
      const authResult = await this.authService.verifyToken(token);
      request.user = authResult.user;

      // Extract active store context
      const headerStore = request.headers['x-store-id'];
      const queryStore = request.query?.store_id;
      request.storeId = headerStore ? parseInt(headerStore, 10) : (queryStore ? parseInt(queryStore, 10) : request.user.store_id || null);

      return true;
    } catch {
      throw new UnauthorizedException('Phiên đăng nhập đã hết hạn hoặc không hợp lệ');
    }
  }
}
