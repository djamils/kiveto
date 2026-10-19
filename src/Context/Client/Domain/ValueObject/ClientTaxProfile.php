<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

use App\Context\Client\Domain\Exception\InvalidClientTaxProfileException;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\Siren;

/**
 * The client's tax status and, when it has one, its legal name (« raison
 * sociale »). The person identity of the client then becomes the contact
 * person. SIREN and VAT number only exist for a French VAT-liable business.
 */
final class ClientTaxProfile
{
    private const int LEGAL_NAME_MAX_LENGTH = 255;

    private ClientTaxStatus $status;
    private ?string $legalName;
    private ?Siren $siren;
    private ?FrenchVatNumber $vatNumber;

    private function __construct(ClientTaxStatus $status, ?string $legalName, ?Siren $siren, ?FrenchVatNumber $vatNumber)
    {
        $this->status    = $status;
        $this->legalName = $legalName;
        $this->siren     = $siren;
        $this->vatNumber = $vatNumber;
    }

    public static function default(): self
    {
        return new self(ClientTaxStatus::INDIVIDUAL, null, null, null);
    }

    public static function individual(?string $legalName): self
    {
        return new self(ClientTaxStatus::INDIVIDUAL, self::normaliseLegalName($legalName), null, null);
    }

    public static function frenchBusiness(string $legalName, Siren $siren, ?FrenchVatNumber $vatNumber): self
    {
        $normalised = self::normaliseLegalName($legalName);

        if (null === $normalised) {
            throw new InvalidClientTaxProfileException(InvalidClientTaxProfileException::LEGAL_NAME_REQUIRED);
        }

        return new self(ClientTaxStatus::FRENCH_BUSINESS, $normalised, $siren, $vatNumber);
    }

    public function status(): ClientTaxStatus
    {
        return $this->status;
    }

    public function legalName(): ?string
    {
        return $this->legalName;
    }

    public function siren(): ?Siren
    {
        return $this->siren;
    }

    public function vatNumber(): ?FrenchVatNumber
    {
        return $this->vatNumber;
    }

    public function hasLegalName(): bool
    {
        return null !== $this->legalName;
    }

    public function equals(self $other): bool
    {
        return $this->status === $other->status
            && $this->legalName === $other->legalName
            && $this->siren?->value() === $other->siren?->value()
            && $this->vatNumber?->value() === $other->vatNumber?->value();
    }

    private static function normaliseLegalName(?string $legalName): ?string
    {
        $trimmed = trim((string) $legalName);

        if ('' === $trimmed) {
            return null;
        }

        if (mb_strlen($trimmed) > self::LEGAL_NAME_MAX_LENGTH) {
            throw new InvalidClientTaxProfileException(InvalidClientTaxProfileException::LEGAL_NAME_TOO_LONG);
        }

        return $trimmed;
    }
}
