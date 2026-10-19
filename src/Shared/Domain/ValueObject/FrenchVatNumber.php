<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * French intra-community VAT number: « FR » + 2-digit key + SIREN, where
 * key = (12 + 3 × (SIREN mod 97)) mod 97. The key check is our own typo guard.
 * Legacy numbers with an alphabetic key are rejected.
 */
final class FrenchVatNumber
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromInput(string $input, Siren $siren): self
    {
        $normalized = strtoupper(str_replace([' ', "\u{00A0}", "\u{202F}", '.'], '', trim($input)));

        if (1 !== preg_match('/^FR(\d{2})(\d{9})$/', $normalized, $parts)) {
            throw new InvalidFrenchVatNumberException(InvalidFrenchVatNumberException::FORMAT, $input);
        }

        if ($parts[2] !== $siren->value()) {
            throw new InvalidFrenchVatNumberException(InvalidFrenchVatNumberException::SIREN_MISMATCH, $input);
        }

        if ($parts[1] !== self::keyOf($siren)) {
            throw new InvalidFrenchVatNumberException(InvalidFrenchVatNumberException::KEY, $input);
        }

        return new self($normalized);
    }

    public static function forSiren(Siren $siren): self
    {
        return new self('FR' . self::keyOf($siren) . $siren->value());
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * « FR 31 523456788 ».
     */
    public function formatted(): string
    {
        return \sprintf('%s %s %s', substr($this->value, 0, 2), substr($this->value, 2, 2), substr($this->value, 4));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private static function keyOf(Siren $siren): string
    {
        return \sprintf('%02d', (12 + 3 * ((int) $siren->value() % 97)) % 97);
    }
}
