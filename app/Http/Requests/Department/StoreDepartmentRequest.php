<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'unique:departments,name',
            ],
            'short_code' => [
                'required',
                'string',
                'max:10',
                'unique:departments,short_code',
            ],
            'color_scheme' => [
                'required',
                'string',
                Rule::in(['cyan', 'pink', 'emerald', 'violet', 'amber', 'slate']),
            ],
            'status' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
