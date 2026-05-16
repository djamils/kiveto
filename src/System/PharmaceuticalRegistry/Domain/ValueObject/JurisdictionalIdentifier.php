<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

final class JurisdictionalIdentifier
{
    private JurisdictionCode $jurisdictionCode;
    private string $authorityCode;
    private string $authorityIdentifier;

    private function __construct(
        JurisdictionCode $jurisdictionCode,
        string $authorityCode,
        string $authorityIdentifier,
    ) {
        if (1 !== preg_match('/^[A-Z_]{3,16}$/', $authorityCode)) {
            throw new \InvalidArgumentException(
                \sprintf('Invalid authority code: "%s". Must match ^[A-Z_]{3,16}$.', $authorityCode),
            );
        }

        $authorityIdentifier = mb_trim($authorityIdentifier);

        if ('' === $authorityIdentifier || mb_strlen($authorityIdentifier) > 64) {
            throw new \InvalidArgumentException('Authority identifier must be non-empty and at most 64 characters.');
        }

        $this->jurisdictionCode    = $jurisdictionCode;
        $this->authorityCode       = $authorityCode;
        $this->authorityIdentifier = $authorityIdentifier;
    }

    public static function of(
        JurisdictionCode $jurisdictionCode,
        string $authorityCode,
        string $authorityIdentifier,
    ): self {
        return new self($jurisdictionCode, $authorityCode, $authorityIdentifier);
    }

    public static function anmv(string $number): self
    {
        return new self(JurisdictionCode::fromString('FR'), 'ANMV', $number);
    }

    public static function ema(string $number): self
    {
        return new self(JurisdictionCode::fromString('EU'), 'EMA', $number);
    }

    public function jurisdictionCode(): JurisdictionCode
    {
        return $this->jurisdictionCode;
    }

    public function authorityCode(): string
    {
        return $this->authorityCode;
    }

    public function authorityIdentifier(): string
    {
        return $this->authorityIdentifier;
    }

    public function equals(self $other): bool
    {
        return $this->jurisdictionCode->equals($other->jurisdictionCode)
            && $this->authorityCode === $other->authorityCode
            && $this->authorityIdentifier === $other->authorityIdentifier;
    }
}
