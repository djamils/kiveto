<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClinicListing\Domain\ClinicListing;
use App\Context\ClinicListing\Domain\ClinicListingId;
use App\Context\ClinicListing\Domain\ListingAddress;
use App\Context\ClinicListing\Domain\ListingCoordinates;
use App\Context\ClinicListing\Domain\ListingProfile;
use App\Context\ClinicListing\Domain\Repository\ClinicListingRepositoryInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;

/**
 * One row per clinic, upserted on `clinic_id`; the listing id is written once
 * and never changed. Coordinates are DECIMAL(8|9, 6), written apart from the
 * rest (a geocoding answer and a refresh never overwrite each other).
 */
final readonly class DbalClinicListingRepository implements ClinicListingRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function save(ClinicListing $listing): void
    {
        $profile     = $listing->profile();
        $address     = $profile->address();
        $coordinates = $listing->coordinates();

        $this->connection->executeStatement(
            'INSERT INTO ' . ListingColumns::LISTINGS
            . ' (clinic_id, id, name, address_line, postal_code, city, phone, lat, lng, address_hash, timezone, opening_hours, active, refreshed_at)'
            . ' VALUES (:clinic, :id, :name, :line, :postal, :city, :phone, :lat, :lng, :hash, :timezone, :hours, :active, :refreshed)'
            . ' AS new ON DUPLICATE KEY UPDATE name = new.name, address_line = new.address_line, postal_code = new.postal_code, city = new.city,'
            // The coordinates belong to saveCoordinates(): kept, unless the address is gone.
            . ' phone = new.phone, lat = IF(new.address_hash IS NULL, NULL, ' . ListingColumns::LISTINGS . '.lat),'
            . ' lng = IF(new.address_hash IS NULL, NULL, ' . ListingColumns::LISTINGS . '.lng),'
            . ' address_hash = new.address_hash, timezone = new.timezone,'
            . ' opening_hours = new.opening_hours, active = new.active, refreshed_at = new.refreshed_at',
            [
                'clinic'    => ListingColumns::binary($listing->clinicId()),
                'id'        => ListingColumns::binary($listing->id()->toString()),
                'name'      => $profile->name(),
                'line'      => $address?->line(),
                'postal'    => $address?->postalCode(),
                'city'      => $address?->city(),
                'phone'     => $profile->phone(),
                'lat'       => null === $coordinates ? null : \sprintf('%.6F', $coordinates->latitude()),
                'lng'       => null === $coordinates ? null : \sprintf('%.6F', $coordinates->longitude()),
                'hash'      => $listing->addressHash(),
                'timezone'  => $profile->timezone(),
                'hours'     => json_encode($profile->openingHours(), \JSON_THROW_ON_ERROR),
                'active'    => $profile->isActive() ? 1 : 0,
                'refreshed' => ListingColumns::utc($listing->refreshedAt()),
            ],
        );
    }

    public function saveCoordinates(ClinicListing $listing): void
    {
        $coordinates = $listing->coordinates();

        $this->connection->executeStatement(
            'UPDATE ' . ListingColumns::LISTINGS . ' SET lat = :lat, lng = :lng WHERE clinic_id = :clinic AND address_hash = :hash',
            [
                'clinic' => ListingColumns::binary($listing->clinicId()),
                'hash'   => $listing->addressHash(),
                'lat'    => null === $coordinates ? null : \sprintf('%.6F', $coordinates->latitude()),
                'lng'    => null === $coordinates ? null : \sprintf('%.6F', $coordinates->longitude()),
            ],
        );
    }

    public function findByClinicId(string $clinicId): ?ClinicListing
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . ListingColumns::LISTINGS . ' WHERE clinic_id = :clinic',
            ['clinic' => ListingColumns::binary($clinicId)],
        );

        if (false === $row) {
            return null;
        }

        $lat = ListingColumns::coordinate($row, 'lat');
        $lng = ListingColumns::coordinate($row, 'lng');

        return ClinicListing::reconstitute(
            id: ClinicListingId::fromString(RowAccessor::uuid($row, 'id')),
            clinicId: RowAccessor::uuid($row, 'clinic_id'),
            profile: new ListingProfile(
                name: RowAccessor::string($row, 'name'),
                address: ListingAddress::fromParts(
                    RowAccessor::nullableString($row, 'address_line'),
                    RowAccessor::nullableString($row, 'postal_code'),
                    RowAccessor::nullableString($row, 'city'),
                ),
                phone: RowAccessor::nullableString($row, 'phone'),
                timezone: RowAccessor::string($row, 'timezone'),
                openingHours: ListingColumns::openingHours(RowAccessor::string($row, 'opening_hours')),
                active: 1 === RowAccessor::int($row, 'active'),
            ),
            coordinates: null === $lat || null === $lng ? null : ListingCoordinates::fromDegrees($lat, $lng),
            addressHash: RowAccessor::nullableString($row, 'address_hash'),
            refreshedAt: new \DateTimeImmutable(RowAccessor::string($row, 'refreshed_at'), new \DateTimeZone('UTC')),
        );
    }
}
