<?php

declare(strict_types=1);

namespace App\Context\Prescription\Infrastructure\Adapter\Clinic;

use App\Context\Prescription\Application\Port\PrescriberDirectoryInterface;
use App\Context\Prescription\Application\Port\PrescriberSnapshotDto;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * The signing identity of a practitioner in a clinic: their display name, their
 * ordinal registration number and their title, read from the staff profile
 * attached to their membership.
 */
final readonly class DbalPrescriberDirectory implements PrescriberDirectoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function forUser(string $userId, string $clinicId): ?PrescriberSnapshotDto
    {
        $row = $this->connection->fetchAssociative(
            'SELECT sp.display_name, sp.first_name, sp.last_name,
                    sp.registration_number, sp.professional_title
             FROM clinic__clinic_memberships m
             INNER JOIN clinic__staff_profiles sp ON sp.membership_id = m.id
             WHERE m.user_id = :userId AND m.clinic_id = :clinicId
             LIMIT 1',
            [
                'userId'   => Uuid::fromString($userId)->toBinary(),
                'clinicId' => Uuid::fromString($clinicId)->toBinary(),
            ],
        );

        if (false === $row) {
            return null;
        }

        $displayName = RowAccessor::nullableString($row, 'display_name');

        if (null === $displayName || '' === mb_trim($displayName)) {
            $displayName = mb_trim(
                (RowAccessor::nullableString($row, 'first_name') ?? '')
                . ' '
                . (RowAccessor::nullableString($row, 'last_name') ?? ''),
            );
        }

        if ('' === $displayName) {
            return null;
        }

        return new PrescriberSnapshotDto(
            userId: $userId,
            displayName: $displayName,
            registrationNumber: RowAccessor::nullableString($row, 'registration_number'),
            professionalTitle: RowAccessor::nullableString($row, 'professional_title'),
        );
    }
}
