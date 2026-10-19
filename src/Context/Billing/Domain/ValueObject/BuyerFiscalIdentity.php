<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\Siren;

/**
 * Who the buyer is for the tax authority, frozen with the invoice at issue.
 *
 * A French business is invoiced under its legal name, SIREN (BT-47) and VAT
 * number (BT-48), at its postal address (BG-8), and its invoices are routed
 * to its electronic address (BT-49). Kiveto proposes the default address
 * itself — scheme 0225 + the SIREN — kept as two fields: the platform
 * directory may route it elsewhere when the invoice is sent.
 */
final class BuyerFiscalIdentity
{
    public const string DEFAULT_ELECTRONIC_ADDRESS_SCHEME = '0225';

    /**
     * @param list<string> $streetLines
     */
    private function __construct(
        private readonly BuyerTaxStatus $taxStatus,
        private readonly ?string $legalName,
        private readonly ?Siren $siren,
        private readonly ?FrenchVatNumber $vatNumber,
        private readonly array $streetLines,
        private readonly ?string $postalCode,
        private readonly ?string $city,
        private readonly string $countryCode,
    ) {
    }

    /**
     * @param list<string> $streetLines
     */
    public static function of(
        BuyerTaxStatus $taxStatus,
        ?string $legalName,
        ?Siren $siren,
        ?FrenchVatNumber $vatNumber,
        array $streetLines,
        ?string $postalCode,
        ?string $city,
        string $countryCode = 'FR',
    ): self {
        $lines = [];
        foreach ($streetLines as $line) {
            $line = mb_trim($line);

            if ('' !== $line) {
                $lines[] = $line;
            }
        }

        return new self(
            $taxStatus,
            self::clean($legalName),
            $siren,
            $vatNumber,
            $lines,
            self::clean($postalCode),
            self::clean($city),
            mb_strtoupper(mb_trim($countryCode)),
        );
    }

    public function taxStatus(): BuyerTaxStatus
    {
        return $this->taxStatus;
    }

    public function isFrenchBusiness(): bool
    {
        return BuyerTaxStatus::FRENCH_BUSINESS === $this->taxStatus;
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

    /**
     * The electronic address scheme (BT-49-1): 0225 when the SIREN is known.
     */
    public function electronicAddressScheme(): ?string
    {
        return null !== $this->siren ? self::DEFAULT_ELECTRONIC_ADDRESS_SCHEME : null;
    }

    /**
     * The electronic address identifier (BT-49): the SIREN.
     */
    public function electronicAddress(): ?string
    {
        return $this->siren?->value();
    }

    /**
     * @return list<string>
     */
    public function streetLines(): array
    {
        return $this->streetLines;
    }

    public function postalCode(): ?string
    {
        return $this->postalCode;
    }

    public function city(): ?string
    {
        return $this->city;
    }

    public function countryCode(): string
    {
        return $this->countryCode;
    }

    public function hasCompleteAddress(): bool
    {
        return [] !== $this->streetLines && null !== $this->postalCode && null !== $this->city;
    }

    public function equals(self $other): bool
    {
        return $this->taxStatus === $other->taxStatus
            && $this->legalName === $other->legalName
            && $this->siren?->value() === $other->siren?->value()
            && $this->vatNumber?->value() === $other->vatNumber?->value()
            && $this->streetLines === $other->streetLines
            && $this->postalCode === $other->postalCode
            && $this->city === $other->city
            && $this->countryCode === $other->countryCode;
    }

    private static function clean(?string $value): ?string
    {
        $value = null === $value ? '' : mb_trim($value);

        return '' === $value ? null : $value;
    }
}
