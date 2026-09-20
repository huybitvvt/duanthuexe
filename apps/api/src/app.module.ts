import { Module } from '@nestjs/common';
import { ConfigModule } from '@nestjs/config';
import { DatabaseModule } from './database/database.module';
import { HealthModule } from './modules/health/health.module';
import { AuthModule } from './modules/auth/auth.module';
import { StoreModule } from './modules/store/store.module';
import { VehicleModule } from './modules/vehicle/vehicle.module';
import { CustomerModule } from './modules/customer/customer.module';
import { PricingModule } from './modules/pricing/pricing.module';
import { LeadModule } from './modules/lead/lead.module';
import { UserModule } from './modules/user/user.module';
import { OrderModule } from './modules/order/order.module';
import { FinanceModule } from './modules/finance/finance.module';
import { DashboardModule } from './modules/dashboard/dashboard.module';
import { ReportModule } from './modules/report/report.module';
import { MaintenanceModule } from './modules/maintenance/maintenance.module';
import { HrModule } from './modules/hr/hr.module';
import { AccountingModule } from './modules/accounting/accounting.module';
import { WarehouseModule } from './modules/warehouse/warehouse.module';
import { LeaseModule } from './modules/lease/lease.module';
import { ReminderModule } from './modules/reminder/reminder.module';

@Module({
  imports: [
    ConfigModule.forRoot({
      isGlobal: true,
      envFilePath: ['.env.local', '.env'],
    }),
    DatabaseModule,
    HealthModule,
    AuthModule,
    StoreModule,
    VehicleModule,
    CustomerModule,
    PricingModule,
    LeadModule,
    UserModule,
    OrderModule,
    FinanceModule,
    DashboardModule,
    ReportModule,
    MaintenanceModule,
    HrModule,
    AccountingModule,
    WarehouseModule,
    LeaseModule,
    ReminderModule,
  ],
})
export class AppModule {}
