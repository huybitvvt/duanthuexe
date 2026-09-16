<?php

namespace App\Http\Services;

use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class KpiReportService
{
    public function build(string $startDate, string $endDate, ?int $storeId = null): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        if ($end->lessThan($start)) {
            throw ValidationException::withMessages(['end_date' => 'Ngày kết thúc phải từ ngày bắt đầu trở đi.']);
        }
        if ($start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['end_date' => 'Khoảng báo cáo tối đa là 366 ngày.']);
        }

        $leads = Lead::query()
            ->with(['user:id,name', 'order:id,store_id'])
            ->select([
                'id', 'order_id', 'store_id', 'user_id', 'status', 'source_channel',
                'campaign_name', 'utm_source', 'utm_campaign', 'created_at',
            ])
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->where('status', '<>', 'deleted')
            ->when($storeId, function ($query) use ($storeId) {
                return $query->where('store_id', $storeId);
            })
            ->get();

        $total = $leads->count();
        $converted = $leads->filter(function ($lead) {
            return !empty($lead->order_id);
        })->count();

        return [
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'store_id' => $storeId,
            ],
            'summary' => [
                'total_leads' => $total,
                'converted_leads' => $converted,
                'pending_leads' => $leads->where('status', 'pending')->count(),
                'conversion_rate' => $this->rate($converted, $total),
            ],
            'by_source' => $this->aggregate($leads, function ($lead) {
                return $lead->source_channel
                    ?: $lead->utm_source
                    ?: ($lead->user ? $lead->user->name : 'Landing page Himoto');
            }),
            'by_campaign' => $this->aggregate($leads, function ($lead) {
                return $lead->campaign_name ?: $lead->utm_campaign ?: 'Chưa gắn chiến dịch';
            }),
            'by_staff' => $this->aggregate($leads, function ($lead) {
                return $lead->user ? $lead->user->name : 'Chưa phân công';
            }),
            'by_status' => $leads->groupBy(function ($lead) {
                return $lead->status ?: 'unknown';
            })->map(function (Collection $items, $label) use ($total) {
                return [
                    'label' => $label,
                    'total' => $items->count(),
                    'share' => $this->rate($items->count(), $total),
                ];
            })->values()->sortByDesc('total')->values()->all(),
            'daily' => $leads->groupBy(function ($lead) {
                return Carbon::parse($lead->created_at)->format('Y-m-d');
            })->map(function (Collection $items, $date) {
                $convertedCount = $items->filter(function ($lead) {
                    return !empty($lead->order_id);
                })->count();

                return [
                    'date' => $date,
                    'total' => $items->count(),
                    'converted' => $convertedCount,
                    'conversion_rate' => $this->rate($convertedCount, $items->count()),
                ];
            })->sortBy('date')->values()->all(),
        ];
    }

    private function aggregate(Collection $leads, callable $labelResolver): array
    {
        return $leads->groupBy($labelResolver)->map(function (Collection $items, $label) {
            $converted = $items->filter(function ($lead) {
                return !empty($lead->order_id);
            })->count();

            return [
                'label' => $label ?: 'Không xác định',
                'total' => $items->count(),
                'converted' => $converted,
                'conversion_rate' => $this->rate($converted, $items->count()),
            ];
        })->values()->sortByDesc('total')->values()->all();
    }

    private function rate(int $value, int $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 2) : 0.0;
    }
}
