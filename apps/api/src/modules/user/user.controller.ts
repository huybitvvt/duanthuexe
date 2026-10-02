import {
  Body,
  Controller,
  Delete,
  ForbiddenException,
  Get,
  Param,
  Post,
  Query,
  Req,
  UseGuards,
} from '@nestjs/common';
import { UserService } from './user.service';
import { JwtAuthGuard } from '../auth/auth.guard';

@Controller('api/auth/users')
@UseGuards(JwtAuthGuard)
export class UserController {
  constructor(private readonly userService: UserService) {}

  @Get()
  async index(@Query() query: any, @Req() req: any) {
    const res = await this.userService.findAll(query, req.user);
    return {
      status: 'success',
      data: res,
    };
  }

  @Get('get-staff-by-store')
  async getStaffByStore(@Query('store_id') storeId: string, @Req() req: any) {
    const sId = storeId ? parseInt(storeId, 10) : req.user.store_id;
    if (req.user.role_id !== 1 && sId !== req.user.store_id) {
      throw new ForbiddenException('Bạn chỉ được xem nhân viên của cơ sở được phân công');
    }
    const res = await this.userService.getStaffByStore(sId);
    return {
      status: 'success',
      data: res,
    };
  }

  @Post('store')
  async store(@Body() body: any, @Req() req: any) {
    this.requireAdmin(req.user);
    const res = await this.userService.create(body);
    return {
      status: 'success',
      message: 'Tạo tài khoản thành công',
      data: res,
    };
  }

  @Post('update')
  async update(@Body() body: any, @Req() req: any) {
    this.requireAdmin(req.user);
    const res = await this.userService.update(parseInt(body.id, 10), body);
    return {
      status: 'success',
      message: 'Cập nhật tài khoản thành công',
      data: res,
    };
  }

  @Post('change-password')
  async changePassword(@Body() body: any, @Req() req: any) {
    this.requireAdmin(req.user);
    return this.userService.changePassword(parseInt(body.id, 10), body.password);
  }

  @Post('change-my-password')
  async changeMyPassword(@Body() body: any, @Req() req: any) {
    return this.userService.changePassword(req.user.id, body.new_password || body.password);
  }

  @Delete(':id')
  async destroy(@Param('id') id: string, @Req() req: any) {
    this.requireAdmin(req.user);
    if (parseInt(id, 10) === req.user.id) {
      throw new ForbiddenException('Không thể xóa tài khoản đang đăng nhập');
    }
    return this.userService.delete(parseInt(id, 10));
  }

  private requireAdmin(user: any) {
    if (user.role_id !== 1) {
      throw new ForbiddenException('Bạn không có quyền quản lý tài khoản');
    }
  }
}
