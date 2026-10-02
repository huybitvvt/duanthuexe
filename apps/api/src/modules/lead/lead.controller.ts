import {
  Body,
  Controller,
  Get,
  Param,
  Post,
  Put,
  Query,
  Req,
  UseGuards,
} from '@nestjs/common';
import { LeadService } from './lead.service';
import { JwtAuthGuard } from '../auth/auth.guard';

@Controller('api/auth/leads')
@UseGuards(JwtAuthGuard)
export class LeadController {
  constructor(private readonly leadService: LeadService) {}

  @Get()
  async index(@Query() query: any, @Req() req: any) {
    const storeId = query.store_id || req.storeId;
    const res = await this.leadService.findAll({ ...query, store_id: storeId });
    return {
      status: 'success',
      data: res,
    };
  }

  @Get('unique-users')
  async uniqueUsers() {
    const res = await this.leadService.uniqueUsers();
    return {
      status: 'success',
      data: res,
    };
  }

  @Get(':id')
  async show(@Param('id') id: string) {
    const res = await this.leadService.findById(parseInt(id, 10));
    return {
      status: 'success',
      data: res,
    };
  }

  @Post()
  async create(@Body() body: any) {
    const res = await this.leadService.create(body);
    return {
      status: 'success',
      message: 'Tạo lead thành công',
      data: res,
    };
  }

  @Put(':id')
  async update(@Param('id') id: string, @Body() body: any) {
    const res = await this.leadService.update(parseInt(id, 10), body);
    return {
      status: 'success',
      message: 'Cập nhật lead thành công',
      data: res,
    };
  }

  @Post(':id') // Laravel route allows POST for destroy
  async destroy(@Param('id') id: string) {
    return this.leadService.delete(parseInt(id, 10));
  }
}
