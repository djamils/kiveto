<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\GetClinicSettings;

use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetClinicSettingsHandler
{
    public function __construct(
        private ClinicRepositoryInterface $clinicRepository,
    ) {
    }

    public function __invoke(GetClinicSettings $query): ?ClinicSettingsView
    {
        $clinic = $this->clinicRepository->findById(ClinicId::fromString($query->clinicId));

        if (null === $clinic) {
            return null;
        }

        $letterhead     = $clinic->letterhead();
        $address        = $letterhead->address;
        $hours          = $clinic->openingHours()->toArray();
        $billing        = $clinic->billingSettings();
        $billingAddress = $billing->billingAddress;

        return new ClinicSettingsView(
            id: $clinic->id()->toString(),
            name: $clinic->name(),
            slug: $clinic->slug()->toString(),
            status: $clinic->status()->value,
            timeZone: $clinic->timeZone()->toString(),
            locale: $clinic->locale()->toString(),
            countryCode: $clinic->countryCode()->toString(),
            currencyCode: $clinic->currencyCode()->toString(),
            letterhead: [
                'legalName'             => $letterhead->legalName,
                'legalForm'             => $letterhead->legalForm,
                'shareCapital'          => $letterhead->shareCapital,
                'siret'                 => $letterhead->siret,
                'vatNumber'             => $letterhead->vatNumber,
                'rcsCity'               => $letterhead->rcsCity,
                'rcsNumber'             => $letterhead->rcsNumber,
                'apeCode'               => $letterhead->apeCode,
                'ordinalNumber'         => $letterhead->ordinalNumber,
                'streetLine1'           => $address?->streetLine1,
                'streetLine2'           => $address?->streetLine2,
                'postalCode'            => $address?->postalCode,
                'city'                  => $address?->city,
                'countryCode'           => $address?->countryCode,
                'phone'                 => $letterhead->phone?->toString(),
                'email'                 => $letterhead->email?->toString(),
                'website'               => $letterhead->website,
                'ordinalCouncil'        => $letterhead->ordinalCouncil?->value,
                'liabilityInsurer'      => $letterhead->liabilityInsurer,
                'liabilityPolicyNumber' => $letterhead->liabilityPolicyNumber,
            ],
            week: $hours['week'],
            closures: $hours['closures'],
            billing: [
                'streetLine1'                  => $billingAddress?->streetLine1,
                'streetLine2'                  => $billingAddress?->streetLine2,
                'postalCode'                   => $billingAddress?->postalCode,
                'city'                         => $billingAddress?->city,
                'countryCode'                  => $billingAddress?->countryCode,
                'invoiceNumberPrefix'          => $billing->invoiceNumberPrefix,
                'paymentTermsDays'             => $billing->paymentTermsDays,
                'iban'                         => $billing->iban,
                'bic'                          => $billing->bic,
                'invoiceFooterNote'            => $billing->invoiceFooterNote,
                'creditNotePrefix'             => $billing->creditNotePrefix,
                'professionalPaymentTermsDays' => $billing->professionalPaymentTermsDays,
                'vatRegime'                    => $billing->vatRegime?->value,
                'vatOnDebits'                  => $billing->vatOnDebits,
                'acceptedPaymentMethods'       => $billing->acceptedPaymentMethods,
            ],
            brandColor: $clinic->brandColor()?->toString(),
            logoKey: $clinic->logoKey(),
            smsSender: $clinic->messagingSettings()->smsSender,
            overageCapSegments: $clinic->messagingSettings()->overageCapSegments,
        );
    }
}
