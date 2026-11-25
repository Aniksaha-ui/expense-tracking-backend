<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SyncRoleMenusRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $fixedPaths = array_column(config('menu.fixed_routes', []), 'path');

        return [
            'role' => ['required', 'string', 'max:191'],
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
            'role.required' => 'Please select a role.',
            'paths.*.in' => 'One or more selected routes are not valid application routes.',
        ];
    }
}
