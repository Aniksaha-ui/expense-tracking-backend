<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SyncUserMenusRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $fixedPaths = array_column(config('menu.fixed_routes', []), 'path');

        return [
            'paths' => ['present', 'array'],
            'paths.*' => ['string', Rule::in($fixedPaths)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paths.*.in' => 'One or more selected routes are not valid application routes.',
        ];
    }
}
