<?php

declare(strict_types=1);

namespace App\Fixtures\System\FeatureCatalog\Story;

use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\Context\Clinic\Story\ConsoleDemoStory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\FeatureCatalog\Application\Command\ApplyStartingFeatures\ApplyStartingFeatures;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use Doctrine\DBAL\Connection;
use Zenstruck\Foundry\Story;

/**
 * The features of the console demo (F1–F3): the demo clinics have the
 * modules they use, Lyon tries electronic invoicing (Bêta), the Calanques
 * (Kiveto's own) allow writing in support sessions; the clinics created
 * from the console started with the default features.
 */
final class FeatureCatalogDemoStory extends Story
{
    private const string NAME = 'Djamil Soidiki';

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly Connection $connection,
    ) {
    }

    public function build(): void
    {
        $base     = [FeatureCatalogue::ONLINE_BOOKING, FeatureCatalogue::SMS_REMINDERS];
        $adminId  = $this->adminId();
        $starting = [
            ClinicDataStory::INDEPENDENT_CLINIC_ID => [$base, '-40 days'],
            ClinicDataStory::GROUP_CLINIC_ID       => [[...$base, FeatureCatalogue::ELECTRONIC_INVOICING], '-14 days'],
            ClinicDataStory::CALM_CLINIC_ID        => [[...$base, FeatureCatalogue::ELECTRONIC_INVOICING, FeatureCatalogue::SUPPORT_WRITE_MODE], '-60 days'],
            ConsoleDemoStory::VIEUX_PORT_ID        => [$base, '-4 days'],
            ConsoleDemoStory::ALPILLES_ID          => [[FeatureCatalogue::ONLINE_BOOKING], '-9 days'],
            ConsoleDemoStory::COTE_BLEUE_ID        => [$base, '-75 days'],
        ];

        foreach ($starting as $clinicId => [$codes, $at]) {
            $this->commandBus->dispatch(new ApplyStartingFeatures($clinicId, $codes, $adminId, self::NAME));
            $this->connection->executeStatement(
                'UPDATE feature_catalog__clinic_features SET changed_at = :at WHERE clinic_id = UUID_TO_BIN(:clinic)',
                ['at' => (new \DateTimeImmutable($at))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'clinic' => $clinicId],
            );
        }
    }

    private function adminId(): ?string
    {
        $id = $this->connection->fetchOne("SELECT BIN_TO_UUID(id) FROM identity_access__users WHERE email = 'admin@kiveto.local'");

        return \is_string($id) ? $id : null;
    }
}
