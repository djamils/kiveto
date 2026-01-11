<?php

declare(strict_types=1);

namespace App\ClinicAccess\Application\Query\GetUserMembershipInClinic;

use App\Clinic\Domain\ValueObject\ClinicId;
use App\ClinicAccess\Domain\Repository\ClinicMembershipRepositoryInterface;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;

final readonly class GetUserMembershipInClinicHandler
{
    public function __construct(
        private ClinicMembershipRepositoryInterface $membershipRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GetUserMembershipInClinic $query): ?MembershipDetails
    {
        $userId   = UserId::fromString($query->userId);
        $clinicId = ClinicId::fromString($query->clinicId);

        $membership = $this->membershipRepository->findByClinicAndUser($clinicId, $userId);

        if (null === $membership) {
            return null;
        }

        $isEffectiveNow = $membership->isEffectiveAt($this->clock->now());

        return new MembershipDetails(
            membershipId: $membership->id()->toString(),
            role: $membership->role(),
            engagement: $membership->engagement(),
            status: $membership->status(),
            validFrom: $membership->validFrom(),
            validUntil: $membership->validUntil(),
            isEffectiveNow: $isEffectiveNow,
        );
    }
}
