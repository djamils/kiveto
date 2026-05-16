<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

final class JurisdictionalControlCode
{
    private JurisdictionCode $jurisdictionCode;
    private string $code;

    private function __construct(JurisdictionCode $jurisdictionCode, string $code)
    {
        $this->jurisdictionCode = $jurisdictionCode;
        $this->code             = $code;
    }

    public static function of(JurisdictionCode $jurisdictionCode, string $code): self
    {
        return new self($jurisdictionCode, $code);
    }

    public function jurisdictionCode(): JurisdictionCode
    {
        return $this->jurisdictionCode;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function equals(self $other): bool
    {
        return $this->jurisdictionCode->equals($other->jurisdictionCode)
            && $this->code === $other->code;
    }
}
