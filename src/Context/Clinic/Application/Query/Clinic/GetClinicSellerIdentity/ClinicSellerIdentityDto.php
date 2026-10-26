<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\GetClinicSellerIdentity;

/**
 * The clinic as the seller named on its invoices. Fiscal identifiers are only
 * given when they are valid: a stored SIRET or VAT number that does not hold is
 * reported as missing rather than printed.
 */
final readonly class ClinicSellerIdentityDto
{
    /**
     * @param list<string> $streetLines
     */
    public function __construct(
        public string $clinicId,
        /** The legal name on the letterhead, else the clinic's name. */
        public string $legalName,
        /** 9 digits, only when the letterhead SIRET is a valid one. */
        public ?string $siren,
        /** Only when it is the VAT number of that SIREN. */
        public ?string $vatNumber,
        /** The address invoices are issued from: the billing address, else the letterhead's. */
        public array $streetLines,
        public ?string $postalCode,
        public ?string $city,
        public string $countryCode,
        /** « SELARL au capital de 30 000 €, RCS Paris 812 456 903, code APE 7500Z » — null when none is filled in. */
        public ?string $legalSentence,
        public ?int $professionalPaymentTermsDays,
        public ?string $iban,
        public ?string $bic,
        /** The prefix invoices are actually numbered under, the default one included. */
        public string $invoicePrefix,
        public string $creditNotePrefix,
        /** False when another establishment of the same SIREN numbers under one of these prefixes (older data). */
        public bool $numberingIsUnique,
    ) {
    }
}
