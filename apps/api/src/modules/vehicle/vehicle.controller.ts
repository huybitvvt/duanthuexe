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
import { VehicleService } from './vehicle.service';
import { JwtAuthGuard } from '../auth/auth.guard';

@Controller('api/auth/vehicle')
@UseGuards(JwtAuthGuard)
export class VehicleController {
  constructor(private readonly vehicleService: VehicleService) {}

  @Get('vehicles')
  async index(@Query() query: any, @Req() req: any) {
    const storeId = query.store_id || req.storeId;
    const res = await this.vehicleService.findAll({ ...query, store_id: storeId });
    return {
      status: 'success',
      data: res,
    };
  }

  @Get('vehicles_with_revenue')
  async indexWithRevenue(@Query() query: any, @Req() req: any) {
    const storeId = query.store_id || req.storeId;
    const res = await this.vehicleService.findAll({ ...query, store_id: storeId });
    return {
      status: 'success',
      data: res,
    };
  }

  @Get('vehicles/report')
  async report(@Query() query: any, @Req() req: any) {
    const storeId = query.store_id || req.storeId;
    const res = await this.vehicleService.getReport({ store_id: storeId });
    return {
      status: 'success',
      data: res,
    };
  }

  @Get('vehicles/:id')
  async show(@Param('id') id: string) {
    const res = await this.vehicleService.findById(parseInt(id, 10));
    return {
      status: 'success',
      data: res,
    };
  }

  @Post('vehicles/store')
  async store(@Body() body: any, @Req() req: any) {
    const res = await this.vehicleService.create(body, req.user?.id);
    return {
      status: 'success',
      message: 'Tạo xe mới thành công',
      data: res,
    };
  }

  @Post('vehicles/update')
  async update(@Body() body: any) {
    const id = body.id;
    const res = await this.vehicleService.update(parseInt(id, 10), body);
    return {
      status: 'success',
      message: 'Cập nhật thông tin xe thành công',
      data: res,
    };
  }

  @Delete('vehicles/:id')
  async destroy(@Param('id') id: string) {
    return this.vehicleService.delete(parseInt(id, 10));
  }
}
