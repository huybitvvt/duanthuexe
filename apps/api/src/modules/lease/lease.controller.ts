import { Controller, Get, Query, UseGuards } from '@nestjs/common';
import { LeaseService } from './lease.service';
import { JwtAuthGuard } from '../auth/auth.guard';

@Controller('api/auth/lease-contracts')
@UseGuards(JwtAuthGuard)
export class LeaseController {
  constructor(private readonly leaseService: LeaseService) {}

  @Get()
  async index(@Query() query: any) {
    const data = await this.leaseService.findAll(query);
    return { status: 'success', data };
  }
}
