<?php

namespace App\Http\Requests\Claims;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClaimTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('claim_types', 'key'),
            ],
            'label' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->missing('is_active')) {
            $this->merge(['is_active' => true]);
        }
        if ($this->missing('sort_order')) {
            $this->merge(['sort_order' => 0]);
        }
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
