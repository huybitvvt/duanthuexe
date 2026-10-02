<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ValidateApiQuery
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET')) {
            $query = $request->query();
            $rules = [
                'page' => 'nullable|integer|min:1|max:2147483647',
                'per_page' => 'nullable|integer|min:1|max:1000',
                'limit' => 'nullable|integer|min:1|max:1000',
                // Bank selectors use zero for accounts owned by the company.
                'store_id' => 'nullable|integer|min:0',
                'keyword' => 'nullable|string|max:255',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date'.($request->filled('start_date') ? '|after_or_equal:start_date' : ''),
                'dates' => 'nullable|array|size:2',
                'dates.0' => 'required_with:dates|date',
                'dates.1' => 'required_with:dates|date|after_or_equal:dates.0',
            ];
            if ($request->is('api/auth/daily-cash-registers/history')) {
                $rules['from_date'] = 'nullable|date_format:Y-m-d';
                $rules['to_date'] = 'nullable|date_format:Y-m-d'.($request->filled('from_date') ? '|after_or_equal:from_date' : '');
            }
            if ($request->is('api/auth/hr/duty-schedules')) {
                $rules['date'] = 'nullable|date_format:Y-m-d';
            }
            if ($request->is('api/auth/leads', 'api/auth/report/*', 'api/auth/order/car-rental', 'api/auth/export/general_reports')) {
                $rules['source'] = 'nullable|array';
                $rules['source.*'] = ['required', function ($attribute, $value, $fail) {
                    if ($value !== 'NULL' && (!is_scalar($value) || !preg_match('/^[0-9]+$/', (string) $value))) {
                        $fail('Nguồn khách phải là mã nhân viên hoặc NULL.');
                    }
                }];
            }
            Validator::make($query, $rules)->validate();
        }
        return $next($request);
    }
}
