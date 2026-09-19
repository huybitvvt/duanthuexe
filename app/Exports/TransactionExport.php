<?php

namespace App\Exports;

use App\Http\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class TransactionExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStrictNullComparison, WithEvents, WithTitle
{
    private $params;
    private $transactionService;

    public function __construct($params, TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
        $this->params = $params;
    }

    public function collection(): Collection
    {
        $query = $this->transactionService->getListByParams($this->params);

        return $query
            ->leftJoin('stores', 'stores.id', '=', 'transactions.store_id')
            ->select(
                'transactions.id',
                'transactions.created_at',
                'transactions.type',
                'transactions.payment_method',
                'transactions.value',
                'transactions.note',
                'transactions.status',
                'users.name as user_name',
                'stores.store_name',
                'banks.bank_name',
                'banks.owner_name',
                'banks.account_number'
            )
            ->orderBy('transactions.id', 'DESC')
            ->get();
    }

    public function map($transaction): array
    {
        $typeLabel = 'Thu';
        if ($transaction->type === 'out') {
            $typeLabel = 'Chi';
        } elseif ($transaction->type === 'addon') {
            $typeLabel = 'Thu phụ phí';
        }

        $paymentMethodLabel = 'Tiền mặt';
        if ($transaction->payment_method == 2 || $transaction->payment_method === 'bank') {
            $bankDetails = array_filter([
                $transaction->bank_name,
                $transaction->owner_name,
                $transaction->account_number,
            ]);
            $paymentMethodLabel = 'Chuyển khoản' . (!empty($bankDetails) ? ' - ' . implode(' - ', $bankDetails) : '');
        }

        $createdAt = '';
        if ($transaction->created_at) {
            try {
                $createdAt = Carbon::parse($transaction->created_at)->format('d/m/Y H:i');
            } catch (\Exception $e) {
                $createdAt = (string) $transaction->created_at;
            }
        }

        $status = $transaction->status ?: 'Hoàn thành';

        return [
            '#' . $transaction->id,
            $createdAt,
            $transaction->user_name ?: 'N/A',
            $transaction->store_name ?: 'Toàn hệ thống',
            $typeLabel,
            $paymentMethodLabel,
            number_format((float) ($transaction->value ?: 0), 0, ',', '.') . ' đ',
            $transaction->note ?: '',
            $status,
        ];
    }

    public function headings(): array
    {
        return [
            'Mã giao dịch',
            'Ngày giao dịch',
            'Người thực hiện',
            'Cửa hàng',
            'Loại giao dịch',
            'Phương thức thanh toán',
            'Số tiền',
            'Ghi chú',
            'Trạng thái',
        ];
    }

    public function title(): string
    {
        return 'Lịch sử thu chi';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // Style the header row
                $headerRange = 'A1:' . $highestColumn . '1';
                $sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($headerRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('0BB783'); // Emerald brand color
                $sheet->getStyle($headerRange)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(30);

                if ($highestRow > 1) {
                    $sheet->getStyle('A2:A' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('B2:B' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('C2:C' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle('D2:D' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle('E2:E' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('F2:F' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle('G2:G' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle('G2:G' . $highestRow)->getFont()->setBold(true);
                    $sheet->getStyle('H2:H' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle('I2:I' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $allRange = 'A1:' . $highestColumn . $highestRow;
                    $sheet->getStyle($allRange)->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setRGB('E4E6EF');
                    $sheet->getStyle($allRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                    for ($row = 2; $row <= $highestRow; $row++) {
                        $sheet->getRowDimension($row)->setRowHeight(22);
                    }
                }
            },
        ];
    }
}
