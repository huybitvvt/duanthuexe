<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class OperationalSchema
{
    private const REQUIRED_MIGRATIONS = [
        'lease' => [
            '2026_09_16_000006_add_reversal_fields_to_lease_payment_allocations_table',
        ],
        'cash_register' => [
            '2026_09_16_000007_add_bank_expenses_to_daily_cash_registers_table',
        ],
        'attendance' => [
            '2026_09_16_000008_create_staff_attendances_table',
        ],
        'kpi' => [
            '2026_09_16_000009_add_attribution_to_leads_table',
        ],
        'accounting' => [
            '2026_09_17_000010_create_accounting_vat_and_assets_tables',
        ],
    ];

    /**
     * Columns that must exist before a money-sensitive pilot workflow runs.
     *
     * Keep this list small and explicit. It is intentionally separate from the
     * migration table so a partially-applied migration is detected as unsafe.
     */
    private const REQUIREMENTS = [
        'lease' => [
            'lease_payment_allocations' => [
                'status',
                'reversal_transaction_id',
                'reversal_reason',
                'reversed_at',
                'reversed_by',
            ],
            'lease_contracts' => [
                'discount_amount',
                'settled_at',
            ],
        ],
        'cash_register' => [
            'daily_cash_registers' => [
                'other_expense_bank_personal',
                'other_income_bank_personal',
                'other_expense_bank_company',
                'other_income_bank_company',
            ],
        ],
        'attendance' => [
            'staff_attendances' => [
                'staff_id',
                'store_id',
                'attendance_date',
                'clock_in_at',
                'clock_out_at',
                'work_minutes',
                'status',
            ],
        ],
        'kpi' => [
            'leads' => [
                'source_channel',
                'campaign_name',
                'utm_source',
                'utm_campaign',
            ],
        ],
        'accounting' => [
            'accounting_vat_documents' => [
                'document_type', 'invoice_number', 'invoice_date', 'amount_before_tax',
                'vat_rate', 'vat_amount', 'total_amount', 'store_id',
            ],
            'business_assets' => [
                'asset_code', 'name', 'store_id', 'purchase_cost', 'residual_value',
                'depreciation_months', 'status',
            ],
        ],
    ];

    /**
     * Return missing tables/columns for one profile or every profile.
     *
     * @param string|null $profile
     * @return array
     */
    public function missing(?string $profile = null): array
    {
        $requirements = $profile === null
            ? self::REQUIREMENTS
            : [$profile => self::REQUIREMENTS[$profile] ?? []];

        $missing = [];

        foreach ($requirements as $profileName => $tables) {
            if (empty($tables)) {
                $missing[$profileName][] = 'unknown_profile';
                continue;
            }

            foreach ($tables as $table => $columns) {
                if (!Schema::hasTable($table)) {
                    $missing[$profileName][] = $table . '.*';
                    continue;
                }

                foreach ($columns as $column) {
                    if (!Schema::hasColumn($table, $column)) {
                        $missing[$profileName][] = $table . '.' . $column;
                    }
                }
            }
        }

        return array_filter($missing);
    }

    public function isReady(string $profile): bool
    {
        return $this->missing($profile) === [];
    }

    public function report(): array
    {
        $missing = $this->missing();
        $profiles = [];
        foreach (array_keys(self::REQUIREMENTS) as $profile) {
            $profiles[$profile] = [
                'ready' => empty($missing[$profile]),
                'missing' => $missing[$profile] ?? [],
            ];
        }

        return [
            'ready' => $missing === [],
            'profiles' => $profiles,
            'required_migrations' => $this->migrationsFor(),
        ];
    }

    public function migrationsFor(?string $profile = null): array
    {
        if ($profile !== null) {
            return self::REQUIRED_MIGRATIONS[$profile] ?? [];
        }

        $migrations = [];
        foreach (self::REQUIRED_MIGRATIONS as $items) {
            $migrations = array_merge($migrations, $items);
        }

        return array_values(array_unique($migrations));
    }
}
