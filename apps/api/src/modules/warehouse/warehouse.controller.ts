import { Controller, Get, Query, Req, UseGuards } from '@nestjs/common';
import { WarehouseService } from './warehouse.service';
import { JwtAuthGuard } from '../auth/auth.guard';

@Controller('api/auth/warehouses')
@UseGuards(JwtAuthGuard)
export class WarehouseController {
  constructor(private readonly warehouseService: WarehouseService) {}

  @Get()
  async index(@Query() query: any) {
    const data = await this.warehouseService.findAll(query);
    return { status: 'success', data };
  }
}
