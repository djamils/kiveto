<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClinicListing\Application\Port\ClinicListingReadModelInterface;
use App\Context\ClinicListing\Application\Port\ListingRow;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * The directory in SQL: the projection joined to the settings, only `active`
 * and `listed` rows. A search around a point first keeps the rows in a
 * bounding box of the radius (indexed `lat` / `lng`, which also leaves out
 * rows without coordinates), then measures them with the Haversine formula
 * in whole metres; keyset pagination on (distance, id) or (name, id).
 */
final readonly class DbalClinicListingReadModel implements ClinicListingReadModelInterface
{
    private const float EARTH_RADIUS_M    = 6_371_000.0;
    private const float METRES_PER_DEGREE = 111_320.0;
    private const string ESCAPE           = '!';
    private const string COLUMNS          = 'l.id, l.clinic_id, l.name, l.address_line, l.postal_code, l.city, l.phone, l.timezone, l.opening_hours, s.accepts_new_clients, s.tags';
    private const string FROM             = ' FROM ' . ListingColumns::LISTINGS . ' l JOIN ' . ListingColumns::SETTINGS . ' s ON s.clinic_id = l.clinic_id WHERE l.active = 1 AND s.listed = 1';
    private const string DISTANCE         = 'ROUND(:earth * 2 * ASIN(SQRT(POW(SIN(RADIANS(l.lat - :lat) / 2), 2) + COS(RADIANS(:lat)) * COS(RADIANS(l.lat)) * POW(SIN(RADIANS(l.lng - :lng) / 2), 2))))';

    public function __construct(private Connection $connection)
    {
    }

    public function searchByName(?string $q, ?string $afterName, ?string $afterId, int $limit): array
    {
        $sql    = 'SELECT ' . self::COLUMNS . self::FROM;
        $params = [];

        $sql .= $this->matching($q, $params);

        if (null !== $afterName && null !== $afterId) {
            $sql .= ' AND (l.name > :afterName OR (l.name = :afterName AND l.id > :afterId))';
            $params['afterName'] = $afterName;
            $params['afterId']   = ListingColumns::binary($afterId);
        }

        $sql .= ' ORDER BY l.name, l.id LIMIT ' . max(1, $limit);

        return array_map(self::row(...), $this->connection->fetchAllAssociative($sql, $params));
    }

    public function searchByDistance(?string $q, float $latitude, float $longitude, int $radiusM, ?int $afterDistanceM, ?string $afterId, int $limit): array
    {
        $deltaLat = $radiusM / self::METRES_PER_DEGREE;
        $deltaLng = $radiusM / (self::METRES_PER_DEGREE * max(0.01, cos(deg2rad($latitude))));

        $sql = 'SELECT ' . self::COLUMNS . ', ' . self::DISTANCE . ' AS distance_m' . self::FROM
            . ' AND l.lat BETWEEN :minLat AND :maxLat AND l.lng BETWEEN :minLng AND :maxLng';
        $params = [
            'earth'  => self::EARTH_RADIUS_M,
            'lat'    => $latitude,
            'lng'    => $longitude,
            'minLat' => $latitude - $deltaLat,
            'maxLat' => $latitude + $deltaLat,
            'minLng' => $longitude - $deltaLng,
            'maxLng' => $longitude + $deltaLng,
            'radius' => $radiusM,
        ];

        $sql .= $this->matching($q, $params);
        $sql .= ' HAVING distance_m <= :radius';

        if (null !== $afterDistanceM && null !== $afterId) {
            $sql .= ' AND (distance_m > :afterDistance OR (distance_m = :afterDistance AND l.id > :afterId))';
            $params['afterDistance'] = $afterDistanceM;
            $params['afterId']       = ListingColumns::binary($afterId);
        }

        $sql .= ' ORDER BY distance_m, l.id LIMIT ' . max(1, $limit);

        return array_map(self::row(...), $this->connection->fetchAllAssociative($sql, $params));
    }

    public function findListed(string $listingId): ?ListingRow
    {
        if (!Uuid::isValid($listingId)) {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT ' . self::COLUMNS . self::FROM . ' AND l.id = :id',
            ['id' => ListingColumns::binary($listingId)],
        );

        return false === $row ? null : self::row($row);
    }

    /**
     * The name-or-city condition, `%`, `_` and the escape character taken literally.
     *
     * @param array<string, mixed> $params
     */
    private function matching(?string $q, array &$params): string
    {
        if (null === $q) {
            return '';
        }

        $params['q'] = '%' . strtr($q, [self::ESCAPE => self::ESCAPE . self::ESCAPE, '%' => self::ESCAPE . '%', '_' => self::ESCAPE . '_']) . '%';

        return \sprintf(" AND (l.name LIKE :q ESCAPE '%1\$s' OR l.city LIKE :q ESCAPE '%1\$s')", self::ESCAPE);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function row(array $row): ListingRow
    {
        return new ListingRow(
            id: RowAccessor::uuid($row, 'id'),
            clinicId: RowAccessor::uuid($row, 'clinic_id'),
            name: RowAccessor::string($row, 'name'),
            addressLine: RowAccessor::nullableString($row, 'address_line'),
            postalCode: RowAccessor::nullableString($row, 'postal_code'),
            city: RowAccessor::nullableString($row, 'city'),
            phone: RowAccessor::nullableString($row, 'phone'),
            timezone: RowAccessor::string($row, 'timezone'),
            openingHours: ListingColumns::openingHours(RowAccessor::string($row, 'opening_hours')),
            acceptsNewClients: 1 === RowAccessor::int($row, 'accepts_new_clients'),
            tags: ListingColumns::tags(RowAccessor::string($row, 'tags')),
            distanceM: RowAccessor::nullableInt($row, 'distance_m'),
        );
    }
}
