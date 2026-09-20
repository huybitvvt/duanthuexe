import {
  Body,
  Controller,
  Delete,
  Get,
  Param,
  Post,
  Put,
  UseGuards,
} from '@nestjs/common';
import { StoreService } from './store.service';
import { JwtAuthGuard } from '../auth/auth.guard';

@Controller('api/auth/stores')
@UseGuards(JwtAuthGuard)
export class StoreController {
  constructor(private readonly storeService: StoreService) {}

  @Get('all')
  async getAll() {
    const stores = await this.storeService.findAll();
    return {
      status: 'success',
      data: stores,
    };
  }

  @Get()
  async index() {
    const stores = await this.storeService.findAll();
    return {
      status: 'success',
      data: stores,
    };
  }

  @Get(':id')
  async show(@Param('id') id: string) {
    const store = await this.storeService.findById(parseInt(id, 10));
    return {
      status: 'success',
      data: store,
    };
  }

  @Post()
  async create(@Body() body: any) {
    const created = await this.storeService.create(body);
    return {
      status: 'success',
      message: 'Tạo cửa hàng thành công',
      data: created,
    };
  }

  @Put(':id')
  async update(@Param('id') id: string, @Body() body: any) {
    const updated = await this.storeService.update(parseInt(id, 10), body);
    return {
      status: 'success',
      message: 'Cập nhật cửa hàng thành công',
      data: updated,
    };
  }

  @Delete(':id')
  async delete(@Param('id') id: string) {
    return this.storeService.delete(parseInt(id, 10));
  }
}
