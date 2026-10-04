<?php

namespace App\Http\Requests\Api\Transactions;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBulkTransactionRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'transactions' => ['required', 'array', 'min:1', 'max:100'],
            'transactions.*.entry_type' => ['required', Rule::in(['INCOME', 'EXPENSE', 'DEPOSIT'])],
            'transactions.*.account_id' => ['required', 'integer'],
            'transactions.*.category_id' => ['nullable', 'integer'],
            'transactions.*.amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'transactions.*.note' => ['nullable', 'string', 'max:1000'],
            'transactions.*.transaction_date' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('transactions', []) as $index => $transaction) {
                $type = strtoupper((string) ($transaction['entry_type'] ?? ''));
                $hasCategory = ! empty($transaction['category_id']);

                if ($type === 'EXPENSE' && ! $hasCategory) {
                    $validator->errors()->add("transactions.$index.category_id", 'Category is required for expense transactions.');
                }

                if ($type === 'DEPOSIT' && $hasCategory) {
                    $validator->errors()->add("transactions.$index.category_id", 'Deposit transactions cannot use a category.');
                }
            }
        });
    }
}
