<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class OperationalSchema
{
    private const REQUIRED_MIGRATIONS = [
        'lease' => [
            '2026_09_16_000006_add_reversal_fields_to_lease_payment_allocations_table',
        ],
        'lease_document' => [
            '2026_09_17_000012_add_document_snapshot_to_lease_contracts_table',
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
            '2026_09_17_000011_create_double_entry_accounting_tables',
        ],
        'audit' => [
            '2026_09_17_000001_create_audit_events_table',
        ],
        'rbac' => [
            '2026_09_17_000002_seed_rbac_permissions_and_roles',
        ],
        'ownership' => [
            '2026_09_17_000003_create_lease_ownership_tables',
        ],
        'reminder' => [
            '2026_09_17_000004_enhance_customer_reminder_outbox_table',
        ],
        'gps' => [
            '2026_09_17_000005_create_gps_tracking_tables',
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
        'lease_document' => [
            'lease_contracts' => [
                'document_snapshot',
                'document_snapshot_hash',
                'document_snapshot_version',
                'document_snapshot_locked_at',
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
            'accounting_accounts' => [
                'code', 'name', 'type', 'normal_balance', 'is_active',
            ],
            'accounting_periods' => [
                'fiscal_year', 'period_month', 'start_date', 'end_date', 'status',
                'closed_at', 'closed_by', 'reopened_at', 'reopened_by',
            ],
            'journal_entries' => [
                'entry_number', 'entry_date', 'store_id', 'source_type', 'source_id',
                'status', 'description', 'posted_by', 'posted_at', 'idempotency_key',
            ],
            'journal_lines' => [
                'journal_entry_id', 'account_id', 'store_id', 'debit', 'credit',
            ],
            'accounting_reconciliations' => [
                'period_id', 'store_id', 'account_type', 'reconciliation_date',
                'book_balance', 'actual_balance', 'difference', 'status',
            ],
        ],
        'audit' => [
            'audit_events' => [
                'actor_user_id', 'action', 'subject_type', 'subject_id', 'store_id',
                'before_json', 'after_json', 'reason', 'request_id', 'ip_hash', 'created_at',
            ],
        ],
        'rbac' => [
            'roles' => ['slug', 'name'],
            'permissions' => ['slug', 'name'],
            'roles_permissions' => ['role_id', 'permission_id'],
        ],
        'ownership' => [
            'lease_ownership_requests' => [
                'lease_contract_id', 'vehicle_id', 'customer_id', 'store_id', 'status',
                'remaining_debt', 'requested_by', 'approved_by', 'executed_by', 'idempotency_key',
            ],
            'lease_ownership_events' => [
                'ownership_request_id', 'from_status', 'to_status', 'actor_user_id', 'created_at',
            ],
            'vehicle_ownerships' => [
                'vehicle_id', 'customer_id', 'lease_contract_id', 'ownership_request_id', 'transferred_at',
            ],
        ],
        'reminder' => [
            'customer_reminder_outbox' => [
                'status', 'scheduled_at', 'idempotency_key', 'provider', 'provider_message_id',
                'next_attempt_at', 'locked_at', 'retry_count', 'cancel_reason',
            ],
            'reminder_delivery_events' => [
                'outbox_id', 'provider', 'provider_message_id', 'event_type', 'payload_json', 'created_at',
            ],
        ],
        'gps' => [
            'gps_devices' => [
                'vehicle_id', 'provider', 'external_device_id', 'mapping_status', 'last_sync_at',
            ],
            'gps_positions' => [
                'gps_device_id', 'latitude', 'longitude', 'provider_recorded_at', 'received_at', 'normalized_status',
            ],
            'gps_alerts' => [
                'gps_device_id', 'vehicle_id', 'alert_type', 'status', 'opened_at',
            ],
            'gps_recovery_actions' => [
                'vehicle_id', 'status', 'recovery_plan', 'created_by',
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
