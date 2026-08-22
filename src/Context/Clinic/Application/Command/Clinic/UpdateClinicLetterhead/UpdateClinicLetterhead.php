<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\UpdateClinicLetterhead;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Replaces the identity block the clinic prints on its documents. Every field is
 * optional; sending them all null clears the letterhead.
 */
final readonly class UpdateClinicLetterhead implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public ?string $legalName = null,
        public ?string $legalForm = null,
        public ?string $shareCapital = null,
        public ?string $siret = null,
        public ?string $vatNumber = null,
        public ?string $rcsCity = null,
        public ?string $rcsNumber = null,
        public ?string $apeCode = null,
        public ?string $ordinalNumber = null,
        public ?string $streetLine1 = null,
        public ?string $streetLine2 = null,
        public ?string $postalCode = null,
        public ?string $city = null,
        public ?string $countryCode = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $website = null,
    ) {
    }
}
