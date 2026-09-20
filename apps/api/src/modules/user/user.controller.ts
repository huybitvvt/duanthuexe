import {
  Body,
  Controller,
  Delete,
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
    const storeId = query.store_id || req.storeId;
    const res = await this.userService.findAll({ ...query, store_id: storeId });
    return {
      status: 'success',
      data: res,
    };
  }

  @Get('get-staff-by-store')
  async getStaffByStore(@Query('store_id') storeId: string, @Req() req: any) {
    const sId = storeId ? parseInt(storeId, 10) : req.storeId || 1;
    const res = await this.userService.getStaffByStore(sId);
    return {
      status: 'success',
      data: res,
    };
  }

  @Post('store')
  async store(@Body() body: any) {
    const res = await this.userService.create(body);
    return {
      status: 'success',
      message: 'Tạo tài khoản thành công',
      data: res,
    };
  }

  @Post('update')
  async update(@Body() body: any) {
    const res = await this.userService.update(parseInt(body.id, 10), body);
    return {
      status: 'success',
      message: 'Cập nhật tài khoản thành công',
      data: res,
    };
  }

  @Post('change-password')
  async changePassword(@Body() body: any) {
    return this.userService.changePassword(parseInt(body.id, 10), body.password);
  }

  @Post('change-my-password')
  async changeMyPassword(@Body() body: any, @Req() req: any) {
    return this.userService.changePassword(req.user.id, body.new_password || body.password);
  }

  @Delete(':id')
  async destroy(@Param('id') id: string) {
    return this.userService.delete(parseInt(id, 10));
  }
}
