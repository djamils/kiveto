<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Query\GetUserMembershipInClinic;

use App\AccessControl\Domain\Repository\ClinicMembershipRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
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
