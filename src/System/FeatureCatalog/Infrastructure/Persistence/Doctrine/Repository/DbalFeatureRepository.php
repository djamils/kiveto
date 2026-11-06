<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\FeatureCatalog\Domain\ClinicFeature;
use App\System\FeatureCatalog\Domain\FeatureRepositoryInterface;
use App\System\FeatureCatalog\Domain\FeatureSettings;
use App\System\FeatureCatalog\Domain\FeatureStatus;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalFeatureRepository implements FeatureRepositoryInterface
{
    public const string FEATURES = 'feature_catalog__features';
    public const string CLINICS  = 'feature_catalog__clinic_features';

    private const string CLINIC_COLUMNS = 'BIN_TO_UUID(clinic_id) AS clinic_id, code, enabled, changed_at, BIN_TO_UUID(changed_by_id) AS changed_by_id, changed_by_name';

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function settings(string $code): ?FeatureSettings
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM ' . self::FEATURES . ' WHERE code = ?', [$code]);

        if (false === $row) {
            return null;
        }

        $updatedAt = RowAccessor::nullableString($row, 'updated_at');

        return FeatureSettings::reconstitute(
            RowAccessor::string($row, 'code'),
            FeatureStatus::from(RowAccessor::string($row, 'status')),
            (bool) RowAccessor::int($row, 'default_on'),
            null !== $updatedAt ? self::date($updatedAt) : null,
        );
    }

    public function saveSettings(FeatureSettings $settings): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::FEATURES . ' (code, status, default_on, updated_at) VALUES (:code, :status, :default_on, :updated_at) AS new'
            . ' ON DUPLICATE KEY UPDATE status = new.status, default_on = new.default_on, updated_at = new.updated_at',
            [
                'code'       => $settings->code(),
                'status'     => $settings->status()->value,
                'default_on' => $settings->isDefaultOn() ? 1 : 0,
                'updated_at' => null !== $settings->updatedAt() ? self::utc($settings->updatedAt()) : null,
            ],
        );
    }

    public function clinicFeature(string $clinicId, string $code): ?ClinicFeature
    {
        $row = $this->connection->fetchAssociative(
            'SELECT ' . self::CLINIC_COLUMNS . ' FROM ' . self::CLINICS . ' WHERE clinic_id = UUID_TO_BIN(?) AND code = ?',
            [$clinicId, $code],
        );

        return false === $row ? null : self::clinic($row);
    }

    public function saveClinicFeature(ClinicFeature $feature): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::CLINICS . ' (clinic_id, code, enabled, changed_at, changed_by_id, changed_by_name)'
            . ' VALUES (:clinic, :code, :enabled, :changed_at, :by_id, :by_name) AS new'
            . ' ON DUPLICATE KEY UPDATE enabled = new.enabled, changed_at = new.changed_at, changed_by_id = new.changed_by_id, changed_by_name = new.changed_by_name',
            [
                'clinic'     => Uuid::fromString($feature->clinicId)->toBinary(),
                'code'       => $feature->code,
                'enabled'    => $feature->enabled ? 1 : 0,
                'changed_at' => self::utc($feature->changedAt),
                'by_id'      => null !== $feature->changedById ? Uuid::fromString($feature->changedById)->toBinary() : null,
                'by_name'    => $feature->changedByName,
            ],
        );
    }

    public function clinicsOf(string $code): array
    {
        return array_map(self::clinic(...), $this->connection->fetchAllAssociative(
            'SELECT ' . self::CLINIC_COLUMNS . ' FROM ' . self::CLINICS . ' WHERE code = ?',
            [$code],
        ));
    }

    public function featuresOf(string $clinicId): array
    {
        return array_map(self::clinic(...), $this->connection->fetchAllAssociative(
            'SELECT ' . self::CLINIC_COLUMNS . ' FROM ' . self::CLINICS . ' WHERE clinic_id = UUID_TO_BIN(?) ORDER BY code',
            [$clinicId],
        ));
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function clinic(array $row): ClinicFeature
    {
        return new ClinicFeature(
            clinicId: RowAccessor::string($row, 'clinic_id'),
            code: RowAccessor::string($row, 'code'),
            enabled: (bool) RowAccessor::int($row, 'enabled'),
            changedAt: self::date(RowAccessor::string($row, 'changed_at')),
            changedById: RowAccessor::nullableString($row, 'changed_by_id'),
            changedByName: RowAccessor::nullableString($row, 'changed_by_name'),
        );
    }

    private static function date(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }

    private static function utc(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
