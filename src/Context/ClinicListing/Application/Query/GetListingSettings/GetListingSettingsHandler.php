<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Query\GetListingSettings;

use App\Context\ClinicListing\Domain\Repository\ListingSettingsRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetListingSettingsHandler
{
    public function __construct(private ListingSettingsRepositoryInterface $settings)
    {
    }

    public function __invoke(GetListingSettings $query): ListingSettingsView
    {
        $settings = $this->settings->forClinic($query->clinicId);

        return new ListingSettingsView($settings->isListed(), $settings->acceptsNewClients(), $settings->tags());
    }
}
