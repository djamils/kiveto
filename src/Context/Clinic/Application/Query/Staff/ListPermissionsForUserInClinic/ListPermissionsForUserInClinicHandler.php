<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Staff\ListPermissionsForUserInClinic;

use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListPermissionsForUserInClinicHandler
{
    public function __construct(
        private ClinicMembershipRepositoryInterface $memberships,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<string>
     */
    public function __invoke(ListPermissionsForUserInClinic $query): array
    {
        $membership = $this->memberships->findByClinicAndUser(
            ClinicId::fromString($query->clinicId),
            UserId::fromString($query->userId),
        );

        // A membership that is disabled, or not yet — or no longer — in force,
        // grants nothing.
        if (null === $membership || !$membership->isEffectiveAt($this->clock->now())) {
            return [];
        }

        return array_map(
            static fn ($permission): string => $permission->value,
            $membership->permissions(),
        );
    }
}
