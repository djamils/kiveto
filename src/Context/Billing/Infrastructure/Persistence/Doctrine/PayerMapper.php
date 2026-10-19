<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine;

use App\Context\Billing\Domain\ValueObject\BuyerFiscalIdentity;
use App\Context\Billing\Domain\ValueObject\BuyerTaxStatus;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\CreditNoteEntity;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\InvoiceEntity;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\Siren;

/**
 * The payer of an invoice or a credit note, with its frozen tax identity.
 */
final readonly class PayerMapper
{
    public function toDomain(InvoiceEntity|CreditNoteEntity $entity): Payer
    {
        $clientId = $entity->getPayerClientId();

        return Payer::create(
            null !== $clientId ? PayerId::fromString($clientId->toRfc4122()) : null,
            $entity->getPayerName(),
            $entity->getPayerAddressLines(),
            $entity->getPayerAttention(),
            $this->identity($entity),
        );
    }

    public function toEntity(Payer $payer, InvoiceEntity|CreditNoteEntity $entity): void
    {
        $identity = $payer->fiscalIdentity();

        $entity->setPayerFiscalIdentity(
            taxStatus: $identity?->taxStatus()->value,
            legalName: $identity?->legalName(),
            siren: $identity?->siren()?->value(),
            vatNumber: $identity?->vatNumber()?->value(),
            electronicAddressScheme: $identity?->electronicAddressScheme(),
            electronicAddress: $identity?->electronicAddress(),
            street: $identity?->streetLines(),
            postalCode: $identity?->postalCode(),
            city: $identity?->city(),
            countryCode: $identity?->countryCode(),
        );
    }

    private function identity(InvoiceEntity|CreditNoteEntity $entity): ?BuyerFiscalIdentity
    {
        $status = $entity->getPayerTaxStatus();

        if (null === $status) {
            return null;
        }

        $siren = $entity->getPayerSiren();
        $vat   = $entity->getPayerVatNumber();
        $siren = null !== $siren ? Siren::fromInput($siren) : null;

        return BuyerFiscalIdentity::of(
            taxStatus: BuyerTaxStatus::from($status),
            legalName: $entity->getPayerLegalName(),
            siren: $siren,
            vatNumber: null !== $siren && null !== $vat ? FrenchVatNumber::fromInput($vat, $siren) : null,
            streetLines: $entity->getPayerStreet() ?? [],
            postalCode: $entity->getPayerPostalCode(),
            city: $entity->getPayerCity(),
            countryCode: $entity->getPayerCountryCode() ?? 'FR',
        );
    }
}
