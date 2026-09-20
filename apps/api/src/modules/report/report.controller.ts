import { Controller, Get, Query, Req, UseGuards } from '@nestjs/common';
import { ReportService } from './report.service';
import { JwtAuthGuard } from '../auth/auth.guard';

@Controller('api/auth/report')
@UseGuards(JwtAuthGuard)
export class ReportController {
  constructor(private readonly reportService: ReportService) {}

  @Get('quick-report')
  async quickReport(@Query() query: any) {
    const data = await this.reportService.getQuickReport(query);
    return { status: 'success', data };
  }

  @Get('detail-report')
  async detailReport(@Query() query: any, @Req() req: any) {
    const storeId = query.store_id || req.storeId;
    const data = await this.reportService.getDetailReport({ ...query, store_id: storeId });
    return { status: 'success', data };
  }

  @Get('detail-report-new')
  async detailReportNew(@Query() query: any, @Req() req: any) {
    const storeId = query.store_id || req.storeId;
    const data = await this.reportService.getDetailReport({ ...query, store_id: storeId });
    return { status: 'success', data };
  }

  @Get('detail-report-day-by-day')
  async detailReportDayByDay(@Query() query: any, @Req() req: any) {
    const storeId = query.store_id || req.storeId;
    const data = await this.reportService.getDetailReportDayByDay({ ...query, store_id: storeId });
    return { status: 'success', data };
  }

  @Get('kpi')
  async kpi(@Query() query: any, @Req() req: any) {
    const storeId = query.store_id || req.storeId;
    const data = await this.reportService.getKpiReport({ ...query, store_id: storeId });
    return { status: 'success', data };
  }
}
