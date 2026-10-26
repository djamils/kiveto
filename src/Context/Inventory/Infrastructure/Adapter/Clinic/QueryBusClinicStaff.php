<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Adapter\Clinic;

use App\Context\Clinic\Application\Query\Staff\ListClinicMembersForNotification\ListClinicMembersForNotification;
use App\Context\Inventory\Application\Port\ClinicStaffInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Clinic's ListClinicMembersForNotification (effective memberships only).
 */
final readonly class QueryBusClinicStaff implements ClinicStaffInterface
{
    private const string ASSISTANT = 'VETERINARY_ASSISTANT';

    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function assistants(string $clinicId): array
    {
        return $this->ask(new ListClinicMembersForNotification($clinicId, self::ASSISTANT));
    }

    public function administrators(string $clinicId): array
    {
        return $this->ask(new ListClinicMembersForNotification($clinicId, administratorsOnly: true));
    }

    /**
     * @return list<string>
     */
    private function ask(ListClinicMembersForNotification $query): array
    {
        $userIds = $this->queryBus->ask($query);

        return \is_array($userIds) ? array_values(array_filter($userIds, \is_string(...))) : [];
    }
}
