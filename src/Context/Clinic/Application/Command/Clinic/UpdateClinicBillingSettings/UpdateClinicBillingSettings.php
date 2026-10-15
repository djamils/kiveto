<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\UpdateClinicBillingSettings;

/**
 * How the clinic bills, replaced whole.
 */
final readonly class UpdateClinicBillingSettings
{
    public function __construct(
        public string $clinicId,
        public ?string $streetLine1 = null,
        public ?string $streetLine2 = null,
        public ?string $postalCode = null,
        public ?string $city = null,
        public ?string $countryCode = null,
        public ?string $invoiceNumberPrefix = null,
        public ?int $paymentTermsDays = null,
        public ?string $iban = null,
        public ?string $bic = null,
        public ?string $invoiceFooterNote = null,
        public ?string $creditNotePrefix = null,
        public ?int $professionalPaymentTermsDays = null,
        /** MONTHLY, QUARTERLY or SIMPLIFIED; null when the clinic has not said. */
        public ?string $vatRegime = null,
        public bool $vatOnDebits = false,
        /** @var list<string>|null CARD, CASH, CHECK, TRANSFER — null for all of them */
        public ?array $acceptedPaymentMethods = null,
    ) {
    }
}
