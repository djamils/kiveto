<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;
use App\Shared\Domain\ValueObject\PostalAddress;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * A save writes the identity and address once, later saves only move the
 * terms acceptance (a second invitation never overwrites them); only
 * updateIdentity(), the owner's own edit, rewrites them. Dates are written
 * in UTC.
 */
final readonly class DbalOwnerProfileRepository implements OwnerProfileRepositoryInterface
{
    private const string TABLE = 'client_portal__owner_profiles';

    public function __construct(private Connection $connection)
    {
    }

    public function save(OwnerProfile $profile): void
    {
        $address = $profile->address();

        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE
            . ' (user_id, first_name, last_name, address_line1, address_line2, address_postal_code, address_city, address_country, terms_version, terms_accepted_at, created_at)'
            . ' VALUES (:user, :first, :last, :line1, :line2, :postal, :city, :country, :terms, :termsAt, :created)'
            . ' AS new ON DUPLICATE KEY UPDATE terms_version = new.terms_version, terms_accepted_at = new.terms_accepted_at',
            [
                'user'    => SqlValues::binary($profile->userId()),
                'first'   => $profile->firstName(),
                'last'    => $profile->lastName(),
                'line1'   => $address?->streetLine1,
                'line2'   => $address?->streetLine2,
                'postal'  => $address?->postalCode,
                'city'    => $address?->city,
                'country' => $address?->countryCode,
                'terms'   => $profile->termsVersion(),
                'termsAt' => SqlValues::utc($profile->termsAcceptedAt()),
                'created' => SqlValues::utc($profile->createdAt()),
            ],
        );
    }

    public function updateIdentity(OwnerProfile $profile): void
    {
        $address = $profile->address();

        $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET first_name = :first, last_name = :last, address_line1 = :line1, address_line2 = :line2,'
            . ' address_postal_code = :postal, address_city = :city, address_country = :country WHERE user_id = :user',
            [
                'user'    => SqlValues::binary($profile->userId()),
                'first'   => $profile->firstName(),
                'last'    => $profile->lastName(),
                'line1'   => $address?->streetLine1,
                'line2'   => $address?->streetLine2,
                'postal'  => $address?->postalCode,
                'city'    => $address?->city,
                'country' => $address?->countryCode,
            ],
        );
    }

    public function findByUserId(string $userId): ?OwnerProfile
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE user_id = :user',
            ['user' => SqlValues::binary($userId)],
        );

        return false === $row ? null : self::profile($row);
    }

    public function findByUserIds(array $userIds): array
    {
        if ([] === $userIds) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE user_id IN (:users)',
            ['users' => array_map(SqlValues::binary(...), $userIds)],
            ['users' => ArrayParameterType::BINARY],
        );

        $profiles = [];

        foreach ($rows as $row) {
            $profile                      = self::profile($row);
            $profiles[$profile->userId()] = $profile;
        }

        return $profiles;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function profile(array $row): OwnerProfile
    {
        $line1 = RowAccessor::nullableString($row, 'address_line1');

        return OwnerProfile::reconstitute(
            userId: RowAccessor::uuid($row, 'user_id'),
            firstName: RowAccessor::string($row, 'first_name'),
            lastName: RowAccessor::string($row, 'last_name'),
            address: null === $line1 ? null : new PostalAddress(
                streetLine1: $line1,
                city: RowAccessor::string($row, 'address_city'),
                countryCode: RowAccessor::string($row, 'address_country'),
                streetLine2: RowAccessor::nullableString($row, 'address_line2'),
                postalCode: RowAccessor::nullableString($row, 'address_postal_code'),
            ),
            termsVersion: RowAccessor::string($row, 'terms_version'),
            termsAcceptedAt: SqlValues::date(RowAccessor::string($row, 'terms_accepted_at')),
            createdAt: SqlValues::date(RowAccessor::string($row, 'created_at')),
        );
    }
}
