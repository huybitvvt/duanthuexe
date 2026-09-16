<?php

namespace App\Exports;

use App\Http\Services\LeaseContractService;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class LeaseDebtExport implements FromCollection, WithHeadings, WithStrictNullComparison
{
    private $params;
    private $user;
    private $service;

    public function __construct(array $params, User $user, LeaseContractService $service)
    {
        $this->params = $params;
        $this->user = $user;
        $this->service = $service;
    }

    public function collection(): Collection
    {
        $this->params['limit'] = 500;
        $this->params['page'] = 1;
        $contracts = collect();
        do {
            $paginated = $this->service->index($this->params, $this->user);
            $contracts = $contracts->concat($paginated->getCollection());
            $this->params['page']++;
        } while ($paginated->hasMorePages());

        return $contracts->map(function ($c) {
            $bucketLabel = [
                'current' => 'Chưa đến hạn',
                'overdue_1_7' => 'Quá hạn 1-7 ngày',
                'overdue_8_30' => 'Quá hạn 8-30 ngày',
                'overdue_30_plus' => 'Quá hạn >30 ngày',
            ][$c->aging_bucket] ?? $c->aging_bucket;

            return [
                'contract_code' => $c->contract_code,
                'customer_name' => $c->customer ? $c->customer->name : 'N/A',
                'customer_phone' => $c->customer ? " " . $c->customer->phone : '',
                'customer_id_card' => $c->customer ? " " . $c->customer->id_card : '',
                'vehicle_license' => $c->vehicle ? $c->vehicle->license : 'N/A',
                'vehicle_name' => $c->vehicle ? $c->vehicle->name : 'N/A',
                'store_name' => $c->store ? $c->store->store_name : 'Kho Thuê sở hữu',
                'total_amount' => number_format($c->total_amount, 0, ',', '.') . ' đ',
                'total_paid' => number_format($c->total_paid, 0, ',', '.') . ' đ',
                'outstanding_balance' => number_format($c->outstanding_balance, 0, ',', '.') . ' đ',
                'overdue_amount' => number_format($c->overdue_amount, 0, ',', '.') . ' đ',
                'overdue_days' => $c->overdue_days > 0 ? "{$c->overdue_days} ngày" : '0',
                'aging_bucket' => $bucketLabel,
                'assigned_user' => $c->assignedUser ? $c->assignedUser->name : 'N/A',
                'latest_note' => $c->latest_note ? $c->latest_note['content'] : '',
                'appointment_date' => $c->latest_note ? ($c->latest_note['appointment_date'] ?? '') : '',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Mã hợp đồng',
            'Tên khách hàng',
            'Số điện thoại',
            'CCCD / CMND',
            'Biển số xe',
            'Tên xe',
            'Kho quản lý',
            'Tổng giá trị HĐ',
            'Đã thu thực tế',
            'Dư nợ còn lại',
            'Nợ quá hạn',
            'Số ngày trễ hạn',
            'Nhóm tuổi nợ',
            'Nhân viên phụ trách',
            'Ghi chú nhắc nợ mới nhất',
            'Ngày hẹn thanh toán',
        ];
    }
}
