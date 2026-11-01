<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Command\RefreshClinicListing;

use App\Context\ClinicListing\Application\Service\ClinicListingRefresher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RefreshClinicListingHandler
{
    public function __construct(private ClinicListingRefresher $refresher)
    {
    }

    public function __invoke(RefreshClinicListing $command): void
    {
        $this->refresher->refresh($command->clinicId);
    }
}
