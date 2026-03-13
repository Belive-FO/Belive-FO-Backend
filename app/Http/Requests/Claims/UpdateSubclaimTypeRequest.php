<?php

namespace App\Http\Requests\Claims;

use App\Models\SubclaimType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubclaimTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $claimType = $this->route('claimType');
        $subclaimType = $this->route('subclaimType');

        return [
            'key' => [
                'sometimes',
                'string',
                'max:50',
                'regex:/^[a-z0-9\-_]+$/',
                Rule::unique('subclaim_types', 'key')
                    ->where('claim_type_id', $claimType->id)
                    ->ignore($subclaimType->id),
            ],
            'label' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', 'in:'.SubclaimType::STATUS_ACTIVE.','.SubclaimType::STATUS_PENDING_APPROVAL],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
