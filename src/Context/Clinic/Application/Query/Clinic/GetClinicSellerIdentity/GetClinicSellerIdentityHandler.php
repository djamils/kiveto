<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\GetClinicSellerIdentity;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\LetterheadDto;
use App\Context\Clinic\Application\Service\InvoiceNumberingUniqueness;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetClinicSellerIdentityHandler
{
    public function __construct(
        private ClinicRepositoryInterface $clinicRepository,
        private InvoiceNumberingUniqueness $numbering,
    ) {
    }

    public function __invoke(GetClinicSellerIdentity $query): ?ClinicSellerIdentityDto
    {
        $clinicId = ClinicId::fromString($query->clinicId);
        $clinic   = $this->clinicRepository->findById($clinicId);

        if (null === $clinic) {
            return null;
        }

        $letterhead = $clinic->letterhead();
        $billing    = $clinic->billingSettings();
        $address    = $billing->billingAddress ?? $letterhead->address;

        return new ClinicSellerIdentityDto(
            clinicId: $clinic->id()->toString(),
            legalName: $letterhead->legalName ?? $clinic->name(),
            siren: $letterhead->siren()?->value(),
            vatNumber: $letterhead->vatNumber()?->value(),
            streetLines: null === $address ? [] : array_values(array_filter([$address->streetLine1, $address->streetLine2])),
            postalCode: $address?->postalCode,
            city: $address?->city,
            countryCode: $address->countryCode ?? $clinic->countryCode()->toString(),
            legalSentence: LetterheadDto::fromDomain($letterhead)->legalSentence(),
            professionalPaymentTermsDays: $billing->professionalPaymentTermsDays,
            iban: $billing->iban,
            bic: $billing->bic,
            invoicePrefix: $billing->effectiveInvoicePrefix(),
            creditNotePrefix: $billing->effectiveCreditNotePrefix(),
            numberingIsUnique: $this->numbering->isUnique($clinicId, $letterhead, $billing),
        );
    }
}
