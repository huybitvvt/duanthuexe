import { Controller, Get, Header, Query, UseGuards } from '@nestjs/common';
import { ExportService } from './export.service';
import { JwtAuthGuard } from '../auth/auth.guard';

@Controller('api/auth/export')
@UseGuards(JwtAuthGuard)
export class ExportController {
  constructor(private readonly exportService: ExportService) {}

  @Get('vehicles')
  @Header('Content-Type', 'text/csv; charset=utf-8')
  @Header('Content-Disposition', 'attachment; filename="vehicles.csv"')
  async exportVehicles(@Query() query: any) {
    return this.exportService.exportVehicles(query);
  }

  @Get('customers')
  @Header('Content-Type', 'text/csv; charset=utf-8')
  @Header('Content-Disposition', 'attachment; filename="customers.csv"')
  async exportCustomers(@Query() query: any) {
    return this.exportService.exportCustomers(query);
  }

  @Get('orders')
  @Header('Content-Type', 'text/csv; charset=utf-8')
  @Header('Content-Disposition', 'attachment; filename="orders.csv"')
  async exportOrders(@Query() query: any) {
    return this.exportService.exportOrders(query);
  }

  @Get('transactions')
  @Header('Content-Type', 'text/csv; charset=utf-8')
  @Header('Content-Disposition', 'attachment; filename="transactions.csv"')
  async exportTransactions(@Query() query: any) {
    return this.exportService.exportTransactions(query);
  }
}
