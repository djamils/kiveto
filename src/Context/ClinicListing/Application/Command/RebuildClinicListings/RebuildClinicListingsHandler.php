<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Command\RebuildClinicListings;

use App\Context\ClinicListing\Application\Port\ClinicProfileInterface;
use App\Context\ClinicListing\Application\Service\ClinicListingRefresher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RebuildClinicListingsHandler
{
    public function __construct(
        private ClinicProfileInterface $clinics,
        private ClinicListingRefresher $refresher,
    ) {
    }

    public function __invoke(RebuildClinicListings $command): int
    {
        $count = 0;

        foreach ($this->clinics->clinicIds() as $clinicId) {
            if ($this->refresher->refresh($clinicId)) {
                ++$count;
            }
        }

        return $count;
    }
}
