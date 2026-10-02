import { Controller, Get, Query, UseGuards } from '@nestjs/common';
import { ReminderService } from './reminder.service';
import { JwtAuthGuard } from '../auth/auth.guard';

@Controller('api/auth/customer-reminders')
@UseGuards(JwtAuthGuard)
export class ReminderController {
  constructor(private readonly reminderService: ReminderService) {}

  @Get()
  async index(@Query() query: any) {
    const data = await this.reminderService.findAll(query);
    return { status: 'success', data };
  }
}
