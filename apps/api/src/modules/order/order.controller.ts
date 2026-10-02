import {
  Body,
  Controller,
  Delete,
  Get,
  Param,
  Post,
  Put,
  Query,
  Req,
  UseGuards,
} from '@nestjs/common';
import { OrderService } from './order.service';
import { JwtAuthGuard } from '../auth/auth.guard';

@Controller('api/auth/order')
@UseGuards(JwtAuthGuard)
export class OrderController {
  constructor(private readonly orderService: OrderService) {}

  @Get('car-rental')
  async index(@Query() query: any, @Req() req: any) {
    const storeId = query.store_id || req.storeId;
    const res = await this.orderService.findAll({ ...query, store_id: storeId });
    return {
      status: 'success',
      data: res,
    };
  }

  @Get('car-rental/:id')
  async show(@Param('id') id: string) {
    const res = await this.orderService.findById(parseInt(id, 10));
    return {
      status: 'success',
      data: res,
    };
  }

  @Post('car-rental')
  async store(@Body() body: any, @Req() req: any) {
    const res = await this.orderService.create(body, req.user?.id);
    return {
      status: 'success',
      message: 'Tạo đơn thuê xe thành công',
      data: res,
    };
  }

  @Put('car-rental/:id')
  async update(@Param('id') id: string, @Body() body: any) {
    const res = await this.orderService.update(parseInt(id, 10), body);
    return {
      status: 'success',
      message: 'Cập nhật đơn thuê xe thành công',
      data: res,
    };
  }

  @Put('car-rental/deposit/:id')
  async deposit(@Param('id') id: string, @Body() body: any, @Req() req: any) {
    const res = await this.orderService.deposit(parseInt(id, 10), body, req.user?.id);
    return {
      status: 'success',
      message: 'Cập nhật tiền cọc thành công',
      data: res,
    };
  }

  @Post('start-contract')
  async startContract(@Body() body: any, @Req() req: any) {
    const res = await this.orderService.startContract(parseInt(body.order_id, 10), body, req.user?.id);
    return {
      status: 'success',
      message: 'Đã bàn giao xe và kích hoạt hợp đồng',
      data: res,
    };
  }

  @Put('car-rental/complete/:id')
  async complete(@Param('id') id: string, @Body() body: any, @Req() req: any) {
    const res = await this.orderService.complete(parseInt(id, 10), body, req.user?.id);
    return {
      status: 'success',
      message: 'Hoàn thành hợp đồng thuê xe thành công',
      data: res,
    };
  }

  @Post('calc_return_early_amount')
  async calcReturnEarly(@Body() body: any) {
    const res = await this.orderService.calculateEarlyReturn(body);
    return {
      status: 'success',
      data: res,
    };
  }
}
