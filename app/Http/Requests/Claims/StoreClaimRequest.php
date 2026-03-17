<?php

namespace App\Http\Requests\Claims;

use App\Models\Claim;
use App\Models\ClaimType;
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
            'claim_type_id' => ['required', 'integer', 'exists:claim_types,id'],
            'subclaim_type_id' => ['nullable', 'integer', 'exists:subclaim_types,id'],
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', 'string', 'in:'.implode(',', $claimTypes)],
            'amount' => ['required', 'numeric', 'min:0.01', new MileageAmountMatchesCalculation],
            'claim_date' => ['required', 'date', 'before_or_equal:today'],
            'merchant' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', 'in:draft,pending'],
            'category_id' => ['nullable', 'integer', 'exists:claim_categories,id'],
            'metadata' => ['nullable', 'array'],
            'metadata.fields' => ['nullable', 'array'],
            'metadata.fields.*.label' => ['required_with:metadata.fields', 'string', 'max:100'],
            'metadata.fields.*.type' => ['required_with:metadata.fields', 'string', 'in:text,number,date,dropdown,mileage,percentage,photo'],
            'metadata.fields.*.value' => ['nullable'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:jpeg,jpg,png,pdf,gif', 'max:10240'],
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

        // Unwrap nested claim payload: object or JSON string (multipart/form-data)
        $claim = $this->input('claim');
        if (is_string($claim)) {
            $decoded = json_decode($claim, true);
            if (is_array($decoded)) {
                $claim = $decoded;
                $this->merge($claim);
            }
        }
        if (is_array($claim)) {
            $this->merge($claim);
            // Normalize camelCase from frontend (do not map customFields → metadata here; we build metadata.fields below)
            $camelMap = [
                'claimTypeId' => 'claim_type_id',
                'subclaimTypeId' => 'subclaim_type_id',
                'claimDate' => 'claim_date',
            ];
            foreach ($camelMap as $camel => $snake) {
                if (array_key_exists($camel, $claim) && ! array_key_exists($snake, $claim)) {
                    $this->merge([$snake => $claim[$camel]]);
                }
            }
        }

        // Resolve claim_type_id to type (ClaimType.key) for storage
        $claimTypeId = $this->input('claim_type_id');
        if ($claimTypeId !== null && $this->missing('type')) {
            $claimType = ClaimType::find($claimTypeId);
            if ($claimType) {
                $this->merge(['type' => $claimType->key]);
            }
        }

        // Map frontend mileage structure to backend expected format (mileage.from_location, mileage.distance_km, etc.)
        $type = $this->input('type');
        if (in_array($type, [Claim::TYPE_MILEAGE, Claim::TYPE_SPECIAL_MILEAGE], true) && $this->missing('mileage')) {
            $metadata = $this->input('metadata');
            $fromLocation = $this->input('from_location') ?? (is_array($metadata) ? ($metadata['from_location'] ?? null) : null);
            $toLocation = $this->input('to_location') ?? (is_array($metadata) ? ($metadata['to_location'] ?? null) : null);
            $distance = $this->input('distance') ?? (is_array($metadata) ? ($metadata['distance'] ?? null) : null);
            $distanceKm = $distance !== null ? (float) $distance : null;
            $amount = $this->input('amount');
            $amount = $amount !== null && $amount !== '' ? (float) $amount : null;

            $ratePerKm = $this->input('rate_per_km');
            if ($ratePerKm === null || $ratePerKm === '') {
                if ($distanceKm > 0 && $amount > 0) {
                    $ratePerKm = round($amount / $distanceKm, 2);
                } else {
                    $ratePerKm = config('claims.mileage_rate', 0.70);
                }
            } else {
                $ratePerKm = (float) $ratePerKm;
            }

            if ($fromLocation !== null || $toLocation !== null || $distanceKm !== null) {
                $this->merge([
                    'mileage' => [
                        'from_location' => $fromLocation ?? '',
                        'to_location' => $toLocation ?? '',
                        'distance_km' => $distanceKm,
                        'rate_per_km' => $ratePerKm,
                    ],
                ]);
            }
        }

        // Build metadata for storage from frontend customFields (claim.customFields or claim.metadata.fields)
        $claim = $this->input('claim');
        if (is_array($claim)) {
            $customFields = $claim['customFields'] ?? $claim['custom_fields'] ?? null;
            if (is_array($customFields)) {
                $fields = [];
                foreach ($customFields as $f) {
                    $fields[] = [
                        'label' => $f['label'] ?? '',
                        'type' => $f['type'] ?? 'text',
                        'value' => $f['value'] ?? null,
                    ];
                }
                $this->merge(['metadata' => ['fields' => $fields]]);
            }
        }

        // Submit flow: frontend may send status "pending_l1" etc.; backend only accepts draft|pending
        $status = $this->input('status');
        if ($status !== null && $status !== 'draft' && $status !== 'pending') {
            $this->merge(['status' => 'pending']);
        }
        if ($this->missing('status')) {
            $this->merge(['status' => 'draft']);
        }

        // Default claim_date to today when frontend does not send it
        if ($this->missing('claim_date')) {
            $this->merge(['claim_date' => now()->toDateString()]);
        }
    }
}
