<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\VerifyClientTaxProfile;

use App\Shared\Application\Bus\ExternalApiCommandInterface;

/**
 * Asks the company registry and VIES about the client's current SIREN and VAT
 * number. Queued for the external worker; never handled in a transaction.
 */
final readonly class VerifyClientTaxProfile implements ExternalApiCommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $clientId,
    ) {
    }
}
