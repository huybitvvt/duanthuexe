import { Controller, Get, Query, Req, UseGuards } from '@nestjs/common';
import { DashboardService } from './dashboard.service';
import { JwtAuthGuard } from '../auth/auth.guard';

@Controller('api/auth/dashboard')
@UseGuards(JwtAuthGuard)
export class DashboardController {
  constructor(private readonly dashboardService: DashboardService) {}

  @Get('overview')
  async overview(@Query() query: any, @Req() req: any) {
    const storeId = query.store_id || req.storeId;
    const data = await this.dashboardService.getOverview({ ...query, store_id: storeId });
    return { status: 'success', data };
  }

  @Get('report')
  async report(@Query() query: any, @Req() req: any) {
    const storeId = query.store_id || req.storeId;
    const data = await this.dashboardService.getReport({ ...query, store_id: storeId });
    return { status: 'success', data };
  }

  @Get('report-chart')
  async reportChart(@Query() query: any, @Req() req: any) {
    const storeId = query.store_id || req.storeId;
    const data = await this.dashboardService.getReportChart({ ...query, store_id: storeId });
    return { status: 'success', data };
  }
}
