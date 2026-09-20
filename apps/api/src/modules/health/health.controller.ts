import { Controller, Get, Res, HttpStatus } from '@nestjs/common';
import { Response } from 'express';
import { DatabaseService } from '../../database/database.service';

@Controller('health')
export class HealthController {
  constructor(private readonly databaseService: DatabaseService) {}

  @Get()
  async checkHealth(@Res() res: Response) {
    const commit = process.env.RENDER_GIT_COMMIT || process.env.GIT_COMMIT || '2b49116';
    const isDbOk = await this.databaseService.checkHealth();

    if (isDbOk) {
      return res.status(HttpStatus.OK).json({
        status: 'ok',
        service: 'himoto-api',
        database: 'ok',
        commit: commit,
      });
    } else {
      return res.status(HttpStatus.SERVICE_UNAVAILABLE).json({
        status: 'error',
        service: 'himoto-api',
        database: 'unavailable',
        commit: commit,
      });
    }
  }
}
