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

    public static function store(): array
    {
        return [
            'store_id' => 'required|numeric',
            'total' => 'required|numeric',
            'customer_name' => 'required',
            'customer_id_card' => ['required','numeric'],
            // 'items' => 'required|array',
        ];
    }

    public static function update(Request $request, Order $order): array
    {
        return [
            'store_id' => 'required|numeric',
            'total' => 'required|numeric',
            'customer_name' => 'required',
            'customer_id_card' => ['required','numeric', Rule::unique('customers', 'id_card')->ignore($order->customer_id)],
            // 'items' => 'required|array',
        ];
    }

    public static function deposit(): array
    {
        return [
            'amount' => 'required|numeric',
        ];
    }
}
