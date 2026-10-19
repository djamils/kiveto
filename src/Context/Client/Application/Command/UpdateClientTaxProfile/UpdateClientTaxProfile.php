<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\UpdateClientTaxProfile;

use App\Context\Client\Application\Command\CreateClient\ClientTaxProfileDto;

final readonly class UpdateClientTaxProfile
{
    public function __construct(
        public string $clinicId,
        public string $clientId,
        public ClientTaxProfileDto $taxProfile,
    ) {
    }
}
