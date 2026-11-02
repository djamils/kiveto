<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Adapter\Clinic;

use App\Context\Client\Application\Port\ClinicStatusInterface;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Clinic\GetClinicGroup\ClinicGroupDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinicGroup\GetClinicGroup;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Clinic's GetClinic, once per clinic: its status is `active` — and so is
 * its group's, when it has one (GetClinicGroup, once per group): a clinic of
 * a suspended or closed group is inactive, as in the directory.
 */
final readonly class QueryBusClinicStatus implements ClinicStatusInterface
{
    private const string ACTIVE = 'active';

    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function activeClinicIds(array $clinicIds): array
    {
        /** @var array<string, bool> $groups by group id */
        $groups = [];

        return array_values(array_filter($clinicIds, function (string $clinicId) use (&$groups): bool {
            $clinic = $this->queryBus->ask(new GetClinic($clinicId));

            if (!$clinic instanceof ClinicDto || self::ACTIVE !== $clinic->status->value) {
                return false;
            }

            $groupId = $clinic->clinicGroupId;

            return null === $groupId || ($groups[$groupId] ??= $this->groupIsActive($groupId));
        }));
    }

    private function groupIsActive(string $clinicGroupId): bool
    {
        $group = $this->queryBus->ask(new GetClinicGroup($clinicGroupId));

        return !$group instanceof ClinicGroupDto || self::ACTIVE === $group->status->value;
    }
}
