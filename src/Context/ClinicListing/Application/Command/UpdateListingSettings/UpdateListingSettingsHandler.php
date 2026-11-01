<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Command\UpdateListingSettings;

use App\Context\ClinicListing\Application\Service\ClinicListingRefresher;
use App\Context\ClinicListing\Domain\Exception\InvalidListingSettingsException;
use App\Context\ClinicListing\Domain\Repository\ListingSettingsRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateListingSettingsHandler
{
    public function __construct(
        private ListingSettingsRepositoryInterface $settings,
        private ClinicListingRefresher $refresher,
    ) {
    }

    /**
     * @throws InvalidListingSettingsException
     */
    public function __invoke(UpdateListingSettings $command): void
    {
        $settings = $this->settings->forClinic($command->clinicId);
        $settings->update($command->listed, $command->acceptsNewClients, $command->tags);

        $this->settings->save($settings);
        $this->refresher->refresh($command->clinicId);
    }
}
