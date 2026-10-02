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
import { HrService } from './hr.service';
import { JwtAuthGuard } from '../auth/auth.guard';

@Controller('api/auth/hr')
@UseGuards(JwtAuthGuard)
export class HrController {
  constructor(private readonly hrService: HrService) {}

  @Get('staff')
  async getStaff(@Query() query: any, @Req() req: any) {
    const storeId = query.store_id || req.storeId;
    const data = await this.hrService.getStaff({ ...query, store_id: storeId });
    return { status: 'success', data };
  }

  @Get('duty-schedules')
  async getDutySchedules(@Query() query: any, @Req() req: any) {
    const storeId = query.store_id || req.storeId;
    const data = await this.hrService.getDutySchedules({ ...query, store_id: storeId });
    return { status: 'success', data };
  }

  @Post('duty-schedules')
  async createDutySchedule(@Body() body: any) {
    const data = await this.hrService.createDutySchedule(body);
    return { status: 'success', message: 'Tạo ca trực thành công', data };
  }

  @Delete('duty-schedules/:id')
  async deleteDutySchedule(@Param('id') id: string) {
    return this.hrService.deleteDutySchedule(parseInt(id, 10));
  }
}
