<?php


namespace App\ValueObjects;

use InvalidArgumentException;

final readonly class UserId
{
    public int $value;

    public function __construct(int $value)
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("UserId must be a positive integer, got: {$value}");
        }

        $this->value = $value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
