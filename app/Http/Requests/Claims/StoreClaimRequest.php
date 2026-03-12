<?php

namespace App\Http\Requests\Claims;

use App\Models\Claim;
use App\Modules\Claims\Rules\MileageAmountMatchesCalculation;
use Illuminate\Foundation\Http\FormRequest;

class StoreClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Claim::class) ?? false;
    }

    public function rules(): array
    {
        $claimTypes = [
            Claim::TYPE_RECEIPT,
            Claim::TYPE_MILEAGE,
            Claim::TYPE_BUSINESS_TRAVEL,
            Claim::TYPE_MISCELLANEOUS,
            Claim::TYPE_OFFICE,
            Claim::TYPE_OUTSTATION,
            Claim::TYPE_RENOVATION,
            Claim::TYPE_SPECIAL_MILEAGE,
            Claim::TYPE_TRANSPORTATION,
        ];

        $rules = [
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', 'string', 'in:'.implode(',', $claimTypes)],
            'amount' => ['required', 'numeric', 'min:0.01', new MileageAmountMatchesCalculation],
            'claim_date' => ['required', 'date', 'before_or_equal:today'],
            'merchant' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', 'in:draft,pending'],
        ];

        if (in_array($this->input('type'), [Claim::TYPE_MILEAGE, Claim::TYPE_SPECIAL_MILEAGE], true)) {
            $rules['mileage'] = ['required', 'array'];
            $rules['mileage.from_location'] = ['required', 'string', 'max:2000'];
            $rules['mileage.to_location'] = ['required', 'string', 'max:2000'];
            $rules['mileage.distance_km'] = ['required', 'numeric', 'min:0.1'];
            $rules['mileage.rate_per_km'] = ['nullable', 'numeric', 'min:0'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if ($this->missing('status')) {
            $this->merge(['status' => 'draft']);
        }
    }
}
