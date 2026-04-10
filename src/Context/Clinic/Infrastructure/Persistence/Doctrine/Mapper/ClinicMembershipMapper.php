<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Clinic\Domain\Staff\ClinicMembership;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicMembershipEntity;
use Symfony\Component\Uid\Uuid;

final class ClinicMembershipMapper
{
    public function toDomain(ClinicMembershipEntity $entity): ClinicMembership
    {
        return ClinicMembership::reconstitute(
            id: ClinicMembershipId::fromString($entity->getId()->toRfc4122()),
            clinicId: ClinicId::fromString($entity->getClinicId()->toRfc4122()),
            userId: UserId::fromString($entity->getUserId()->toRfc4122()),
            role: $entity->getRole(),
            engagement: $entity->getEngagement(),
            status: $entity->getStatus(),
            validFrom: $entity->getValidFrom(),
            validUntil: $entity->getValidUntil(),
            createdAt: $entity->getCreatedAt(),
            isDefault: $entity->getIsDefault(),
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
        $entity->setIsDefault($membership->isDefault());

        return $entity;
    }
}
