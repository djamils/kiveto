<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\Siren;

/**
 * The clinic as the seller of an invoice: what the Clinic context holds of
 * its letterhead and billing settings, read when a document is issued.
 *
 * The SIREN (BT-30) and VAT number (BT-31) are present only when they check
 * out; numberingIsUnique is false when another establishment with the same
 * SIREN uses the same invoice or credit-note prefix (legacy data).
 */
final readonly class SellerIdentity
{
    public const int DEFAULT_PROFESSIONAL_PAYMENT_TERMS_DAYS = 30;

    /**
     * @param list<string> $streetLines
     */
    public function __construct(
        public string $legalName,
        public ?Siren $siren,
        public ?FrenchVatNumber $vatNumber,
        public array $streetLines,
        public ?string $postalCode,
        public ?string $city,
        public string $countryCode,
        public ?string $legalSentence,
        public ?int $professionalPaymentTermsDays,
        public ?string $iban,
        public ?string $bic,
        public bool $numberingIsUnique,
    ) {
    }

    public function hasCompleteAddress(): bool
    {
        return [] !== $this->streetLines && null !== $this->postalCode && null !== $this->city;
    }

    public function paymentTermsDays(): int
    {
        return $this->professionalPaymentTermsDays ?? self::DEFAULT_PROFESSIONAL_PAYMENT_TERMS_DAYS;
    }
}
