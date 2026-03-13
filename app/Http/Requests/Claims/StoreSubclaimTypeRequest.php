<?php

namespace App\Http\Requests\Claims;

use App\Models\SubclaimType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubclaimTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $claimTypeId = $this->route('claimType')->id;

        return [
            'key' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9\-_]+$/',
                Rule::unique('subclaim_types', 'key')->where('claim_type_id', $claimTypeId),
            ],
            'label' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'in:'.SubclaimType::STATUS_ACTIVE.','.SubclaimType::STATUS_PENDING_APPROVAL],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->missing('status')) {
            $this->merge(['status' => SubclaimType::STATUS_ACTIVE]);
        }
        if ($this->missing('sort_order')) {
            $this->merge(['sort_order' => 0]);
        }
        if ($this->missing('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }
}
