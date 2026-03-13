<?php

namespace App\Http\Requests\Claims;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClaimTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $claimType = $this->route('claimType');

        $rules = [
            'label' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if ($this->has('label')) {
            $rules['key'] = [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('claim_types', 'key')->ignore($claimType->id),
            ];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $label = $this->input('label');
        if (is_string($label) && $label !== '') {
            $this->merge(['key' => $this->slugFromLabel($label)]);
        }
    }

    private function slugFromLabel(string $label): string
    {
        $key = strtolower(trim($label));
        $key = preg_replace('/\s+/', '-', $key);
        $key = preg_replace('/[^a-z0-9\-]/', '', $key);
        $key = $key !== '' ? $key : 'claim-type';

        return substr($key, 0, 50);
    }
}
