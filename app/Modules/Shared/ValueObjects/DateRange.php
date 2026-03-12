<?php

namespace App\Modules\Shared\ValueObjects;

readonly class DateRange
{
    public function __construct(
        public \DateTime $start,
        public \DateTime $end
    ) {
        if ($end < $start) {
            throw new \InvalidArgumentException('End date must be after start date');
        }
    }
}


