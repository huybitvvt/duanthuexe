import {
  Body,
  Controller,
  Delete,
  Get,
  Param,
  Post,
  Put,
  Query,
  UseGuards,
} from '@nestjs/common';
import { CustomerService } from './customer.service';
import { JwtAuthGuard } from '../auth/auth.guard';

@Controller('api/auth/customers')
@UseGuards(JwtAuthGuard)
export class CustomerController {
  constructor(private readonly customerService: CustomerService) {}

  @Get()
  async index(@Query() query: any) {
    const res = await this.customerService.findAll(query);
    return {
      status: 'success',
      data: res,
    };
  }

  @Get('search-by-id-card')
  async searchByIdCard(@Query('id_card') idCard: string) {
    const res = await this.customerService.searchByIdCard(idCard);
    return {
      status: 'success',
      data: res,
    };
  }

  @Get(':id')
  async show(@Param('id') id: string) {
    const res = await this.customerService.findById(parseInt(id, 10));
    return {
      status: 'success',
      data: res,
    };
  }

  @Post()
  async store(@Body() body: any) {
    const res = await this.customerService.create(body);
    return {
      status: 'success',
      message: 'Tạo thông tin khách hàng thành công',
      data: res,
    };
  }

  @Put(':id')
  async update(@Param('id') id: string, @Body() body: any) {
    const res = await this.customerService.update(parseInt(id, 10), body);
    return {
      status: 'success',
      message: 'Cập nhật thông tin khách hàng thành công',
      data: res,
    };
  }

  @Delete(':id')
  async destroy(@Param('id') id: string) {
    return this.customerService.delete(parseInt(id, 10));
  }
}
