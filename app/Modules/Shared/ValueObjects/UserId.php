<?php

namespace App\Modules\Shared\ValueObjects;

readonly class UserId
{
    public function __construct(
        public int $value
    ) {
        if ($value <= 0) {
            throw new \InvalidArgumentException('User ID must be positive');
        }
    }
}


