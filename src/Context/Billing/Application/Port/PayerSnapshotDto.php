<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

use App\Context\Billing\Domain\ValueObject\BuyerFiscalIdentity;
use App\Context\Billing\Domain\ValueObject\BuyerTaxStatus;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\Siren;

final readonly class PayerSnapshotDto
{
    /**
     * @param list<string> $addressLines
     */
    public function __construct(
        public ?string $clientId,
        public string $displayName,
        public array $addressLines = [],
        // A structure's contact person, « Mme Claire Rostand »; null for a person.
        public ?string $attention = null,
        // A professional's tax identity; null for a private client.
        public ?PayerFiscalSnapshotDto $fiscal = null,
    ) {
    }

    /**
     * The payer an invoice is made out to. An identifier the Client context
     * stored but that no longer checks out (a SIREN with a wrong key) is
     * dropped rather than printed: readiness then reports it as missing.
     */
    public function toPayer(): Payer
    {
        return Payer::create(
            null !== $this->clientId ? PayerId::fromString($this->clientId) : null,
            $this->displayName,
            $this->addressLines,
            $this->attention,
            null !== $this->fiscal ? $this->fiscalIdentity($this->fiscal) : null,
        );
    }

    private function fiscalIdentity(PayerFiscalSnapshotDto $fiscal): ?BuyerFiscalIdentity
    {
        $status = BuyerTaxStatus::tryFrom($fiscal->taxStatus);

        if (BuyerTaxStatus::FRENCH_BUSINESS !== $status) {
            return null;
        }

        $siren = null;
        $vat   = null;

        try {
            $siren = null !== $fiscal->siren ? Siren::fromInput($fiscal->siren) : null;
            $vat   = null !== $siren && null !== $fiscal->vatNumber ? FrenchVatNumber::fromInput($fiscal->vatNumber, $siren) : null;
        } catch (\InvalidArgumentException) {
            // Kept as far as it checks out.
        }

        return BuyerFiscalIdentity::of(
            taxStatus: $status,
            legalName: $fiscal->legalName,
            siren: $siren,
            vatNumber: $vat,
            streetLines: $fiscal->streetLines,
            postalCode: $fiscal->postalCode,
            city: $fiscal->city,
            countryCode: $fiscal->countryCode,
        );
    }
}
