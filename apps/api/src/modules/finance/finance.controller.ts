import {
  Body,
  Controller,
  Get,
  Post,
  Query,
  Req,
  UseGuards,
} from '@nestjs/common';
import { FinanceService } from './finance.service';
import { JwtAuthGuard } from '../auth/auth.guard';

@Controller('api/auth')
@UseGuards(JwtAuthGuard)
export class FinanceController {
  constructor(private readonly financeService: FinanceService) {}

  // Banks
  @Get('banks/all')
  async getAllBanks() {
    const res = await this.financeService.findAllBanks();
    return { status: 'success', data: res };
  }

  @Get('banks')
  async indexBanks() {
    const res = await this.financeService.findAllBanks();
    return { status: 'success', data: res };
  }

  // Cash Funds
  @Get('cash/all')
  async getAllCash() {
    const res = await this.financeService.findAllCash();
    return { status: 'success', data: res };
  }

  @Get('cash')
  async indexCash() {
    const res = await this.financeService.findAllCash();
    return { status: 'success', data: res };
  }

  // Transactions
  @Get('transactions')
  async indexTransactions(@Query() query: any, @Req() req: any) {
    const storeId = query.store_id || req.storeId;
    const res = await this.financeService.findAllTransactions({ ...query, store_id: storeId });
    return { status: 'success', data: res };
  }

  @Get('transactions/stats')
  async statsTransactions(@Query() query: any, @Req() req: any) {
    const storeId = query.store_id || req.storeId;
    const res = await this.financeService.getTransactionStats({ store_id: storeId });
    return { status: 'success', data: res };
  }

  @Post('transactions')
  async createTransaction(@Body() body: any, @Req() req: any) {
    const res = await this.financeService.createTransaction(body, req.user?.id);
    return {
      status: 'success',
      message: 'Tạo giao dịch thành công',
      data: res,
    };
  }

  // Daily Cash Register
  @Get('daily-cash-register')
  async indexDailyRegister(@Query() query: any, @Req() req: any) {
    const storeId = query.store_id || req.storeId;
    const res = await this.financeService.findDailyCashRegister({ ...query, store_id: storeId });
    return { status: 'success', data: res };
  }
}
