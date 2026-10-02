import {
  Body,
  Controller,
  Get,
  Post,
  Put,
  Query,
  UseGuards,
} from '@nestjs/common';
import { MaintenanceService } from './maintenance.service';
import { JwtAuthGuard } from '../auth/auth.guard';

@Controller('api/auth')
@UseGuards(JwtAuthGuard)
export class MaintenanceController {
  constructor(private readonly maintenanceService: MaintenanceService) {}

  @Get('maintenance-rules')
  async getRules() {
    const data = await this.maintenanceService.findAllRules();
    return { status: 'success', data };
  }

  @Post('maintenance-rules')
  async saveRulePost(@Body() body: any) {
    const data = await this.maintenanceService.saveRule(body);
    return { status: 'success', data };
  }

  @Put('maintenance-rules')
  async saveRulePut(@Body() body: any) {
    const data = await this.maintenanceService.saveRule(body);
    return { status: 'success', data };
  }

  @Get('maintenance-log')
  async getLogs(@Query() query: any) {
    const data = await this.maintenanceService.findAllLogs(query);
    return { status: 'success', data };
  }

  @Post('maintenance-log')
  async saveLog(@Body() body: any) {
    const data = await this.maintenanceService.saveLog(body);
    return { status: 'success', data };
  }

  @Get('maintenance-schedules')
  async getSchedules(@Query() query: any) {
    const data = await this.maintenanceService.findAllSchedules(query);
    return { status: 'success', data };
  }
}
