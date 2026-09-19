<?php

namespace Tests\Unit;

use App\Exports\ReportExport;
use App\Http\Services\ReportService;
use Mockery;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    public function test_export_can_be_resolved_by_the_container()
    {
        $export = $this->app->make(ReportExport::class, ['params' => []]);

        $this->assertInstanceOf(ReportExport::class, $export);
    }

    public function test_export_contains_summary_and_day_by_day_rows()
    {
        $params = ['dates' => ['2026-07-01', '2026-07-31'], 'store_id' => 2];
        $service = Mockery::mock(ReportService::class);

        $service->shouldReceive('handleDetailReportNew')
            ->twice()
            ->with($params)
            ->andReturn([
                'by_store' => [[
                    'store_id' => 2,
                    'store_name' => '02 Hàng Bút',
                    'total_real_in' => 8321210681,
                    'total_real_refund' => 5775977000,
                    'total_deposit' => 5844854000,
                    'total_renew' => 745641481,
                    'total_rental_fees' => 1730715200,
                    'total_money_early' => -61175000,
                    'total_money_out_date' => 168005000,
                ]],
            ]);

        $service->shouldReceive('detailReportDayByDay')
            ->twice()
            ->with($params)
            ->andReturn([
                'total_deposit' => [['date' => '2026-07-23', 'total_value' => 11500000]],
                'total_renew' => [['date' => '2026-07-23', 'total_value' => 0]],
                'total_rental_fees' => [['date' => '2026-07-23', 'total_value' => 2330000]],
                'total_real_refund' => [['date' => '2026-07-23', 'total_value' => 2000000]],
                'total_money_early' => [],
                'total_money_out_date' => [['date' => '2026-07-23', 'total_value' => 200000]],
            ]);

        $export = new ReportExport($params, $service);
        $rows = $export->collection()->all();

        $this->assertSame('BÁO CÁO TỔNG QUAN THUÊ XE', $rows[0][0]);
        $this->assertSame('02 Hàng Bút', $rows[2][0]);
        $this->assertSame(61175000.0, $rows[2][6]);
        $this->assertSame('CHI TIẾT TỪNG NGÀY - 02 Hàng Bút', $rows[4][0]);
        $this->assertSame('2026-07-23', $rows[7][0]);
        $this->assertSame(13830000.0, $rows[7][1]);
        $this->assertSame(2000000.0, $rows[7][2]);
        $this->assertSame('Báo cáo thuê xe', $export->title());

        $xlsx = Excel::raw($export, ExcelWriter::XLSX);
        $this->assertSame('PK', substr($xlsx, 0, 2));
    }
}
