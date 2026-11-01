<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClinicListing\Domain\ListingSettings;
use App\Context\ClinicListing\Domain\Repository\ListingSettingsRepositoryInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;

final readonly class DbalListingSettingsRepository implements ListingSettingsRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function forClinic(string $clinicId): ListingSettings
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . ListingColumns::SETTINGS . ' WHERE clinic_id = :clinic',
            ['clinic' => ListingColumns::binary($clinicId)],
        );

        if (false === $row) {
            return ListingSettings::defaults($clinicId);
        }

        return ListingSettings::reconstitute(
            clinicId: $clinicId,
            listed: 1 === RowAccessor::int($row, 'listed'),
            acceptsNewClients: 1 === RowAccessor::int($row, 'accepts_new_clients'),
            tags: ListingColumns::tags(RowAccessor::string($row, 'tags')),
        );
    }

    public function save(ListingSettings $settings): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . ListingColumns::SETTINGS . ' (clinic_id, listed, accepts_new_clients, tags) VALUES (:clinic, :listed, :accepts, :tags)'
            . ' AS new ON DUPLICATE KEY UPDATE listed = new.listed, accepts_new_clients = new.accepts_new_clients, tags = new.tags',
            [
                'clinic'  => ListingColumns::binary($settings->clinicId()),
                'listed'  => $settings->isListed() ? 1 : 0,
                'accepts' => $settings->acceptsNewClients() ? 1 : 0,
                'tags'    => json_encode($settings->tags(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
            ],
        );
    }
}
