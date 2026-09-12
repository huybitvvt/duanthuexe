<?php


namespace App\Validators;


use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Prettus\Validator\LaravelValidator;

class UserValidator extends LaravelValidator
{

    public static function store(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|unique:users|max:255',
            'password' => 'required|string',
            'confirm_password' => 'required|string',
            'phone' => 'required|integer',
            'address' => 'required|string',
            'store_id' => 'required|integer',
        ];
    }

    public static function update(Request $request, Order $order): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|unique:users|max:255',
            'password' => 'required|string',
            'confirm_password' => 'required|string',
            'phone' => 'required|integer',
            'address' => 'required|string',
            'store_id' => 'required|integer',
        ];
    }
}
