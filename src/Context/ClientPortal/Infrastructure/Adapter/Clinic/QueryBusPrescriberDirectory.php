<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Clinic;

use App\Context\ClientPortal\Application\Port\PrescriberDirectoryInterface;
use App\Context\Clinic\Application\Query\Staff\ListClinicMembersForNotification\ListClinicMembersForNotification;
use App\Context\Clinic\Application\Query\Staff\ListPermissionsForUserInClinic\ListPermissionsForUserInClinic;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Clinic's ListClinicMembersForNotification (memberships in force, account
 * active) and, per member, ListPermissionsForUserInClinic: a clinic has a
 * handful of members.
 */
final readonly class QueryBusPrescriberDirectory implements PrescriberDirectoryInterface
{
    private const string VETERINARY = 'VETERINARY';

    private const string MANAGE_ANIMALS = 'manage_animals';

    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function veterinarians(string $clinicId): array
    {
        return $this->members(new ListClinicMembersForNotification($clinicId, self::VETERINARY));
    }

    public function animalManagers(string $clinicId): array
    {
        return array_values(array_filter(
            $this->members(new ListClinicMembersForNotification($clinicId)),
            fn (string $userId): bool => \in_array(self::MANAGE_ANIMALS, $this->strings($this->queryBus->ask(new ListPermissionsForUserInClinic($userId, $clinicId))), true),
        ));
    }

    /**
     * @return list<string>
     */
    private function members(ListClinicMembersForNotification $query): array
    {
        return $this->strings($this->queryBus->ask($query));
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $values): array
    {
        return \is_array($values) ? array_values(array_filter($values, \is_string(...))) : [];
    }
}
