import {
  Body,
  Controller,
  Delete,
  Get,
  Param,
  Post,
  Query,
  UseGuards,
} from '@nestjs/common';
import { PricingService } from './pricing.service';
import { JwtAuthGuard } from '../auth/auth.guard';

@Controller('api/auth/priceVehicles')
@UseGuards(JwtAuthGuard)
export class PricingController {
  constructor(private readonly pricingService: PricingService) {}

  @Get()
  async index(@Query() query: any) {
    const res = await this.pricingService.findAll(query);
    return {
      status: 'success',
      data: res,
    };
  }

  @Post()
  async storeOrUpdate(@Body() body: any) {
    const res = await this.pricingService.storeOrUpdate(body);
    return {
      status: 'success',
      message: 'Cập nhật bảng giá thành công',
      data: res,
    };
  }

  @Delete(':id')
  async destroy(@Param('id') id: string) {
    return this.pricingService.delete(parseInt(id, 10));
  }
}
