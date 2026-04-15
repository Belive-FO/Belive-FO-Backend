<?php

namespace App\Http\Requests\Claims;

use App\Models\Claim;
use Illuminate\Foundation\Http\FormRequest;

class RejectClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        $claim = $this->route('claim');
        if (! $claim instanceof Claim) {
            return false;
        }

        return $this->user()?->can('reject', $claim) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
            'level' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }
}
