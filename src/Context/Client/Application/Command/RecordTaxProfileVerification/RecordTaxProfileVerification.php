<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\RecordTaxProfileVerification;

/**
 * Stores the answers the registry and VIES gave for the identifiers that were
 * checked. Short and transactional: no network call.
 */
final readonly class RecordTaxProfileVerification
{
    public function __construct(
        public string $clinicId,
        public string $clientId,
        public string $checkedSiren,
        public ?string $checkedVatNumber,
        public string $companyOutcome,
        public ?string $registryName,
        public string $companyCheckedAt,
        public string $vatOutcome,
        public string $vatCheckedAt,
    ) {
    }
}
