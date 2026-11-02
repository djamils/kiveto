<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Command\AttachListingCoordinates;

use App\Context\ClinicListing\Domain\ListingCoordinates;
use App\Context\ClinicListing\Domain\Repository\ClinicListingRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AttachListingCoordinatesHandler
{
    public function __construct(private ClinicListingRepositoryInterface $listings)
    {
    }

    public function __invoke(AttachListingCoordinates $command): void
    {
        $listing = $this->listings->findByClinicId($command->clinicId);

        if (null === $listing) {
            return;
        }

        $coordinates = null !== $command->latitude && null !== $command->longitude
            ? ListingCoordinates::fromDegrees($command->latitude, $command->longitude)
            : null;

        if ($listing->attachCoordinates($command->addressHash, $coordinates)) {
            $this->listings->saveCoordinates($listing);
        }
    }
}
