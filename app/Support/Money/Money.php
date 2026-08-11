<?php

namespace App\Support\Money;

use InvalidArgumentException;

/**
 * Money value object. Always integer minor units (qəpik); floats are never used
 * for money (R-DOM-15 / R-CODE-11). Locale-aware formatting happens at the
 * Resource boundary, not here.
 */
final readonly class Money
{
    public function __construct(
        public int $minor,
        public string $currency = 'AZN',
    ) {}

    public static function ofMinor(int $minor, string $currency = 'AZN'): self
    {
        return new self($minor, $currency);
    }

    public static function zero(string $currency = 'AZN'): self
    {
        return new self(0, $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }

    /**
     * @return array{minor:int, currency:string}
     */
    public function toArray(): array
    {
        return ['minor' => $this->minor, 'currency' => $this->currency];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}."
            );
        }
    }
}
