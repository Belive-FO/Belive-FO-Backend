<?php

namespace App\Modules\Claims\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;

class MileageAmountMatchesCalculation implements ValidationRule, DataAwareRule, ValidatorAwareRule
{
    protected array $data = [];

    protected ?Validator $validator = null;

    private const TOLERANCE = 0.01;

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Validate that amount equals distance_km * rate_per_km within tolerance (±0.01).
     * Only runs when type is mileage or special-mileage and mileage data is present.
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        $type = $this->data['type'] ?? null;
        $mileage = $this->data['mileage'] ?? null;

        if (! in_array($type, ['mileage', 'special-mileage'], true) || ! is_array($mileage)) {
            return;
        }

        $distanceKm = isset($mileage['distance_km']) ? (float) $mileage['distance_km'] : 0.0;
        $ratePerKm = isset($mileage['rate_per_km']) ? (float) $mileage['rate_per_km'] : 0.0;
        $amount = (float) $value;

        $expected = round($distanceKm * $ratePerKm, 2);
        $diff = abs($amount - $expected);

        if ($diff > self::TOLERANCE) {
            $fail('The :attribute must equal distance_km × rate_per_km (expected '.number_format($expected, 2).').');
        }
    }
}
