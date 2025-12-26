<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence\Doctrine\Mapper;

use App\IdentityAccess\Domain\User as DomainUser;
use App\IdentityAccess\Domain\UserId;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;

final class UserMapper
{
    public function toEntity(DomainUser $domainUser): User
    {
        $entity = new User();
        $entity->setId($domainUser->id()->toString());
        $entity->setEmail($domainUser->email());
        $entity->setPasswordHash($domainUser->passwordHash());
        $entity->setCreatedAt($domainUser->createdAt());

        return $entity;
    }

    public function toDomain(User $entity): DomainUser
    {
        return DomainUser::reconstitute(
            UserId::fromString($entity->getId()),
            $entity->getEmail(),
            $entity->getPasswordHash(),
            $entity->getCreatedAt(),
        );
    }
}
