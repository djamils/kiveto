<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * French company identifier (9 digits). A pasted SIRET (14 digits) is reduced
 * to its SIREN. The Luhn check is our own typo guard, not a norm rule.
 */
final class Siren
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromInput(string $input): self
    {
        $digits = str_replace([' ', "\u{00A0}", "\u{202F}", '.', '-'], '', trim($input));

        if (1 === preg_match('/^\d{14}$/', $digits)) {
            $digits = substr($digits, 0, 9);
        }

        if (1 !== preg_match('/^\d{9}$/', $digits)) {
            throw new InvalidSirenException(InvalidSirenException::FORMAT, $input);
        }

        if (!self::passesLuhn($digits)) {
            throw new InvalidSirenException(InvalidSirenException::CHECKSUM, $input);
        }

        return new self($digits);
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * « 356 000 000 ».
     */
    public function formatted(): string
    {
        return implode(' ', str_split($this->value, 3));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private static function passesLuhn(string $digits): bool
    {
        $sum = 0;

        foreach (array_reverse(str_split($digits)) as $position => $digit) {
            $n = (int) $digit;

            if (1 === $position % 2) {
                $n *= 2;
                $n = $n > 9 ? $n - 9 : $n;
            }

            $sum += $n;
        }

        return 0 === $sum % 10;
    }
}
