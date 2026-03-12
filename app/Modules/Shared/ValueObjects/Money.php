<?php

namespace App\Modules\Shared\ValueObjects;

readonly class Money
{
    public function __construct(
        public int $amount, // in cents
        public string $currency = 'MYR'
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }
    }
    
    public function toDecimal(): float
    {
        return $this->amount / 100;
    }
    
    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Cannot add different currencies');
        }
        
        return new self($this->amount + $other->amount, $this->currency);
    }
}


