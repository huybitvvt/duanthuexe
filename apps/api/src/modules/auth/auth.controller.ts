import {
  Body,
  Controller,
  Get,
  Headers,
  HttpCode,
  HttpStatus,
  Post,
  Req,
  UseGuards,
} from '@nestjs/common';
import { AuthService } from './auth.service';
import { JwtAuthGuard } from './auth.guard';

@Controller('api/auth')
export class AuthController {
  constructor(private readonly authService: AuthService) {}

  @Post('login')
  @HttpCode(HttpStatus.OK)
  async login(@Body() body: { email?: string; password?: string }) {
    if (!body.email || !body.password) {
      return {
        statusCode: 422,
        message: 'Vui lòng cung cấp đầy đủ email và mật khẩu',
      };
    }
    return this.authService.login(body.email, body.password);
  }

  @Post('verify-token')
  @HttpCode(HttpStatus.OK)
  async verifyToken(@Headers('authorization') authHeader: string) {
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return {
        statusCode: 401,
        message: 'Unauthorized',
      };
    }
    const token = authHeader.substring(7).trim();
    return this.authService.verifyToken(token);
  }

  @Get('me')
  @UseGuards(JwtAuthGuard)
  async me(@Req() req: any) {
    const user = req.user;
    const capabilities = this.authService.getCapabilities(user);
    return {
      user,
      capabilities,
    };
  }

  @Post('logout')
  @HttpCode(HttpStatus.OK)
  async logout() {
    return {
      message: 'User successfully signed out',
    };
  }

  @Post('refresh')
  @HttpCode(HttpStatus.OK)
  async refresh(@Headers('authorization') authHeader: string) {
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return {
        statusCode: 401,
        message: 'Unauthorized',
      };
    }
    const token = authHeader.substring(7).trim();
    return this.authService.verifyToken(token);
  }
}
