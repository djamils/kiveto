<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\RequestTaxProfileVerification;

/**
 * « Revérifier » on the client sheet.
 */
final readonly class RequestTaxProfileVerification
{
    public function __construct(
        public string $clinicId,
        public string $clientId,
    ) {
    }
}
