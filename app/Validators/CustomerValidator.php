<?php

namespace App\Validators;

class CustomerValidator
{
    public static function rules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';
        return [
            'name' => $required.'|required|string|max:191',
            'phone' => array_merge(['bail', $required, 'required', 'string', 'max:30'], OrderValidator::contractRules()['customer_phone']),
            'email' => 'nullable|email|max:191',
            'address' => 'nullable|string|max:1000',
            'id_card' => 'nullable|string|max:30',
            'status' => 'sometimes|required|integer|in:0,1,2',
            'warning' => 'nullable|string|max:1000',
            'id_card_issued_on' => 'nullable|date',
            'id_card_issued_by' => 'nullable|string|max:191',
            'relatives' => 'nullable|array',
            'relatives.*' => 'array',
            'relatives.*.name' => 'nullable|string|max:191',
            'relatives.*.phone' => 'nullable|string|max:30',
            'relatives.*.relationship' => 'nullable|string|max:191',
        ];
    }
}
