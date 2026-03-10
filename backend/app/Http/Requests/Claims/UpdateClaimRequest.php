<?php

namespace App\Http\Requests\Claims;

use App\Models\Claim;
use App\Modules\Claims\Rules\MileageAmountMatchesCalculation;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        $claim = $this->route('claim');
        if (! $claim instanceof Claim || $claim->status !== Claim::STATUS_DRAFT) {
            return false;
        }

        return $this->user()?->can('update', $claim) ?? false;
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
            'title' => ['sometimes', 'string', 'max:200'],
            'type' => ['sometimes', 'string', 'in:'.implode(',', $claimTypes)],
            'amount' => ['sometimes', 'numeric', 'min:0.01', new MileageAmountMatchesCalculation],
            'claim_date' => ['sometimes', 'date', 'before_or_equal:today'],
            'merchant' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];

        $type = $this->input('type', $this->route('claim')?->type);
        if (in_array($type, [Claim::TYPE_MILEAGE, Claim::TYPE_SPECIAL_MILEAGE], true)) {
            $rules['mileage'] = ['sometimes', 'array'];
            $rules['mileage.from_location'] = ['required_with:mileage', 'string', 'max:2000'];
            $rules['mileage.to_location'] = ['required_with:mileage', 'string', 'max:2000'];
            $rules['mileage.distance_km'] = ['required_with:mileage', 'numeric', 'min:0.1'];
            $rules['mileage.rate_per_km'] = ['nullable', 'numeric', 'min:0'];
        }

        return $rules;
    }
}
