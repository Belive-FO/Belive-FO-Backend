<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');

        return $department && ($this->user()?->can('update', $department) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $departmentId = $this->route('department')?->id ?? $this->route('department');

        return [
            'name' => [
                'sometimes',
                'string',
                'min:2',
                'max:255',
                Rule::unique('departments', 'name')->ignore($departmentId),
            ],
            'short_code' => [
                'sometimes',
                'required',
                'string',
                'max:10',
                Rule::unique('departments', 'short_code')->ignore($departmentId),
            ],
            'color_scheme' => [
                'sometimes',
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
