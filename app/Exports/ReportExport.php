<?php

namespace App\Exports;

use App\Http\Services\ReportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportExport implements FromCollection, ShouldAutoSize, WithStrictNullComparison, WithTitle
{
    private $reportService;
    private $params;

    public function __construct($params, ReportService $reportService)
    {
        $this->reportService = $reportService;
        $this->params = $params;
    }

    public function collection(): Collection
    {
        $summary = $this->reportService->handleDetailReportNew($this->params);
        $stores = $summary['by_store'] ?? [];
        $rows = [
            ['BÁO CÁO TỔNG QUAN THUÊ XE'],
            ['Cửa hàng', 'Thu thực tế', 'Chi thực tế', 'Thu cọc', 'Thu gia hạn', 'Phí thuê', 'Trả sớm', 'Phạt muộn'],
        ];

        foreach ($stores as $store) {
            $rows[] = $this->summaryRow($store);
        }

        if (empty($stores)) {
            $rows[] = ['Không có dữ liệu'];
        }

        foreach ($stores as $store) {
            $rows[] = [];
            $rows[] = ['CHI TIẾT TỪNG NGÀY - ' . ($store['store_name'] ?? '')];
            $rows[] = ['Ngày', 'Thu thực tế', 'Chi thực tế', 'Thu cọc', 'Thu gia hạn', 'Phí thuê', 'Trả sớm', 'Phạt muộn'];
            $rows[] = $this->storeTotalRow($store);

            $detailParams = $this->params;
            $detailParams['store_id'] = $store['store_id'];
            $details = $this->reportService->detailReportDayByDay($detailParams);

            foreach ($this->detailDates($details) as $date) {
                $deposit = $this->detailValue($details, 'total_deposit', $date);
                $renew = $this->detailValue($details, 'total_renew', $date);
                $rentalFees = $this->detailValue($details, 'total_rental_fees', $date);

                $rows[] = [
                    $date,
                    $deposit + $renew + $rentalFees,
                    $this->detailValue($details, 'total_real_refund', $date),
                    $deposit,
                    $renew,
                    $rentalFees,
                    $this->detailValue($details, 'total_money_early', $date),
                    $this->detailValue($details, 'total_money_out_date', $date),
                ];
            }
        }

        return new Collection($rows);
    }

    public function title(): string
    {
        return 'Báo cáo thuê xe';
    }

    private function summaryRow(array $store): array
    {
        return [
            $store['store_name'] ?? '',
            (float) ($store['total_real_in'] ?? 0),
            (float) ($store['total_real_refund'] ?? 0),
            (float) ($store['total_deposit'] ?? 0),
            (float) ($store['total_renew'] ?? 0),
            (float) ($store['total_rental_fees'] ?? 0),
            abs((float) ($store['total_money_early'] ?? 0)),
            (float) ($store['total_money_out_date'] ?? 0),
        ];
    }

    private function storeTotalRow(array $store): array
    {
        $row = $this->summaryRow($store);
        $row[0] = 'Tổng';

        return $row;
    }

    private function detailDates(array $details): array
    {
        $dates = [];
        foreach ($details as $items) {
            foreach ($items as $item) {
                $date = is_array($item) ? ($item['date'] ?? null) : ($item->date ?? null);
                if ($date) {
                    $dates[$date] = true;
                }
            }
        }

        $dates = array_keys($dates);
        rsort($dates);

        return $dates;
    }

    private function detailValue(array $details, string $field, string $date): float
    {
        foreach ($details[$field] ?? [] as $item) {
            $itemDate = is_array($item) ? ($item['date'] ?? null) : ($item->date ?? null);
            if ($itemDate === $date) {
                $value = is_array($item) ? ($item['total_value'] ?? 0) : ($item->total_value ?? 0);
                return abs((float) $value);
            }
        }

        return 0;
    }
}
