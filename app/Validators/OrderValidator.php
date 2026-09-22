<?php


namespace App\Validators;


use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Prettus\Validator\LaravelValidator;

class OrderValidator extends LaravelValidator
{
    const ORDER_COMPLETED = 'completed';
    const ORDER_RENTING = 'renting';
    const ORDER_UNPAID = 'wait_payment';
    const ORDER_TYPE_RENTING = 'rental';
    const ORDER_BAD_DEBT = 'bad_debt';
    const ORDER_DEPOSIT_CONTRACT = 'deposit_contract'; // Loại hợp đồng khách đặt cọc để giữ xe.
    const ORDER_DRAFT = 'draft';

    public static function contractRules(): array
    {
        return [
			'customer_phone' => [function ($attribute, $value, $fail) {
				if ($value === null || $value === '') {
					return;
				}
				$phone = preg_replace('/[\s().-]+/', '', (string) $value);
				$validVietnamese = preg_match('/^(?:0\d{9}|\+84\d{9})$/', $phone);
				$validInternational = preg_match('/^\+[1-9]\d{7,14}$/', $phone);
				if (!$validVietnamese && !$validInternational) {
					$fail('Số điện thoại phải bắt đầu bằng 0 hoặc +84 (Việt Nam), hoặc + mã nước ngoài và đủ số.');
				}
			}],
            'contract_signed_on' => 'nullable|date',
            'contract_authorization_date' => 'nullable|date',
            'contract_authorization_party_name' => 'nullable|string|max:191',
            'contract_collateral_description' => 'nullable|string|max:1000',
            'contract_signer_a_name' => 'nullable|string|max:191',
            'contract_signer_b_name' => 'nullable|string|max:191',
            'customer_source' => 'nullable|string|max:191',
            'customer_source_url' => 'nullable|url|max:1000',
            'save_as_draft' => 'nullable|boolean',
            'draft_reference' => 'nullable|string|max:32',
            'manual_contract_number' => 'nullable|string|max:32',
            'customer_id_card_issued_on' => 'nullable|date',
            'id_card_issued_on' => 'nullable|date',
            'customer_id_card_issued_by' => 'nullable|string|max:191',
            'id_card_issued_by' => 'nullable|string|max:191',
            'relatives' => 'nullable|array|max:2',
            'relatives.*.name' => 'nullable|string|max:191',
            'relatives.*.phone' => 'nullable|string|max:30',
            'relatives.*.relationship' => 'nullable|string|max:100',
            'order_items' => 'nullable|array',
            'order_items.*.borrow_raincoats' => 'nullable|integer|min:0',
            'order_items.*.pricing_scheme' => 'nullable|in:flat_200k_day',
            'order_items.*.driver_name' => 'nullable|string|max:191',
            'order_items.*.driver_license_number' => 'nullable|string|max:50',
            'order_items.*.driver_license_issued_on' => 'nullable|date',
        ];
    }

    public static function store(?Request $request = null): array
    {
        $draft = filter_var(($request ?: request())->get('save_as_draft', false), FILTER_VALIDATE_BOOLEAN);
        return array_merge(self::contractRules(), [
            'store_id' => 'required|numeric',
            'total' => 'required|numeric',
            'customer_name' => 'required',
            'customer_phone' => array_merge([$draft ? 'nullable' : 'required'], self::contractRules()['customer_phone']),
            'customer_id_card' => [$draft ? 'nullable' : 'required', 'regex:/^(?:\d{9}|\d{12})$/'],
            'manual_contract_number' => ['nullable', 'string', 'max:32', Rule::unique('orders', 'contract_number'), Rule::unique('orders', 'draft_reference')],
            'draft_reference' => ['nullable', 'string', 'max:32', Rule::unique('orders', 'draft_reference'), Rule::unique('orders', 'contract_number')],
        ]);
    }

    public static function update(Request $request, Order $order): array
    {
        $draft = filter_var($request->get('save_as_draft', false), FILTER_VALIDATE_BOOLEAN) && $order->order_status === self::ORDER_DRAFT;
        return array_merge(self::contractRules(), [
            'store_id' => 'required|numeric',
            'total' => 'required|numeric',
            'customer_name' => 'required',
            'customer_phone' => array_merge([$draft ? 'nullable' : 'required'], self::contractRules()['customer_phone']),
            'customer_id_card' => [$draft ? 'nullable' : 'required', 'regex:/^(?:\d{9}|\d{12})$/', Rule::unique('customers', 'id_card')->ignore($order->customer_id)],
            'manual_contract_number' => ['nullable', 'string', 'max:32', Rule::unique('orders', 'contract_number')->ignore($order->id), Rule::unique('orders', 'draft_reference')->ignore($order->id)],
            'draft_reference' => ['nullable', 'string', 'max:32', Rule::unique('orders', 'draft_reference')->ignore($order->id), Rule::unique('orders', 'contract_number')->ignore($order->id)],
        ]);
    }

    public static function deposit(): array
    {
        return [
            'amount' => 'required|numeric',
        ];
    }

    public static function complete(): array
    {
        return [
            'return_signer_a_name' => 'nullable|string|max:191',
            'return_signer_b_name' => 'nullable|string|max:191',
            'return_additional_note' => 'nullable|string|max:1000',
        ];
    }
}
