import {
  Body,
  Controller,
  Get,
  Post,
  Query,
  Req,
  UseGuards,
} from '@nestjs/common';
import { AccountingService } from './accounting.service';
import { JwtAuthGuard } from '../auth/auth.guard';

@Controller('api/auth/accounting')
@UseGuards(JwtAuthGuard)
export class AccountingController {
  constructor(private readonly accountingService: AccountingService) {}

  @Get()
  async getSummary(@Query() query: any) {
    const data = await this.accountingService.getSummary(query);
    return { status: 'success', data };
  }

  @Get('accounts')
  async getAccounts() {
    const data = await this.accountingService.getAccounts();
    return { status: 'success', data };
  }

  @Get('journal-entries')
  async getJournalEntries(@Query() query: any) {
    const data = await this.accountingService.getJournalEntries(query);
    return { status: 'success', data };
  }

  @Post('journal-entries')
  async postJournalEntry(@Body() body: any, @Req() req: any) {
    const data = await this.accountingService.postJournalEntry(body, req.user?.id);
    return { status: 'success', message: 'Ghi sổ kế toán thành công', data };
  }
}
