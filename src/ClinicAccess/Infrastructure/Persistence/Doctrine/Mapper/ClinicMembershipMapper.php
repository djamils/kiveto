<?php

declare(strict_types=1);

namespace App\ClinicAccess\Infrastructure\Persistence\Doctrine\Mapper;

use App\Clinic\Domain\ValueObject\ClinicId;
use App\ClinicAccess\Domain\ClinicMembership;
use App\ClinicAccess\Domain\ValueObject\MembershipId;
use App\ClinicAccess\Infrastructure\Persistence\Doctrine\Entity\ClinicMembershipEntity;
use App\IdentityAccess\Domain\ValueObject\UserId;
use Symfony\Component\Uid\Uuid;

final class ClinicMembershipMapper
{
    public function toDomain(ClinicMembershipEntity $entity): ClinicMembership
    {
        return ClinicMembership::reconstitute(
            id: MembershipId::fromString($entity->getId()->toRfc4122()),
            clinicId: ClinicId::fromString($entity->getClinicId()->toRfc4122()),
            userId: UserId::fromString($entity->getUserId()->toRfc4122()),
            role: $entity->getRole(),
            engagement: $entity->getEngagement(),
            status: $entity->getStatus(),
            validFrom: $entity->getValidFrom(),
            validUntil: $entity->getValidUntil(),
            createdAt: $entity->getCreatedAt(),
        );
    }

    public function toEntity(ClinicMembership $membership): ClinicMembershipEntity
    {
        $entity = new ClinicMembershipEntity();
        $entity->setId(Uuid::fromString($membership->id()->toString()));
        $entity->setClinicId(Uuid::fromString($membership->clinicId()->toString()));
        $entity->setUserId(Uuid::fromString($membership->userId()->toString()));
        $entity->setRole($membership->role());
        $entity->setEngagement($membership->engagement());
        $entity->setStatus($membership->status());
        $entity->setValidFrom($membership->validFrom());
        $entity->setValidUntil($membership->validUntil());
        $entity->setCreatedAt($membership->createdAt());

        return $entity;
    }
}
