<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class MenuItemRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $currentId = $this->route('id');
        $fixedPaths = array_column(config('menu.fixed_routes', []), 'path');

        return [
            'path' => ['required', 'string', 'max:191', Rule::in($fixedPaths)],
            'icon' => ['nullable', 'string', 'max:191'],
            'location' => ['nullable', 'string', 'max:191'],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:menu_items,id',
                // A menu item can never be its own parent.
                $currentId ? Rule::notIn([(int) $currentId]) : 'nullable',
            ],
            'order' => ['nullable', 'integer', 'min:0'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'max:191'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'path.required' => 'Please select a menu route.',
            'path.in' => 'The selected route is not a valid application menu route.',
            'parent_id.not_in' => 'A menu item cannot be its own parent.',
        ];
    }
}
