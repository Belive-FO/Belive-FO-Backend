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
            'claim_type_id' => ['sometimes', 'integer', 'exists:claim_types,id'],
            'subclaim_type_id' => ['nullable', 'integer', 'exists:subclaim_types,id'],
            'title' => ['sometimes', 'string', 'max:200'],
            'type' => ['sometimes', 'string', 'in:'.implode(',', $claimTypes)],
            'amount' => ['sometimes', 'numeric', 'min:0.01', new MileageAmountMatchesCalculation],
            'claim_date' => ['sometimes', 'date', 'before_or_equal:today'],
            'merchant' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'metadata' => ['nullable', 'array'],
            'metadata.fields' => ['nullable', 'array'],
            'metadata.fields.*.label' => ['required_with:metadata.fields', 'string', 'max:100'],
            'metadata.fields.*.type' => ['required_with:metadata.fields', 'string', 'in:text,number,date,dropdown,mileage,percentage,photo'],
            'metadata.fields.*.value' => ['nullable'],
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
