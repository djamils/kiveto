<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence\Doctrine\Mapper;

use App\IdentityAccess\Domain\User as DomainUser;
use App\IdentityAccess\Domain\UserId;
use App\IdentityAccess\Domain\UserType;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\ClinicUser;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\PortalUser;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\BackofficeUser;

final class UserMapper
{
    public function toEntity(DomainUser $domainUser): User
    {
        $entity = $this->newEntityForType($domainUser->type());
        $entity->setId($domainUser->id()->toString());
        $entity->setEmail($domainUser->email());
        $entity->setPasswordHash($domainUser->passwordHash());
        $entity->setCreatedAt($domainUser->createdAt());
        $entity->setStatus($domainUser->status());
        $entity->setEmailVerifiedAt($domainUser->emailVerifiedAt());

        return $entity;
    }

    public function toDomain(User $entity): DomainUser
    {
        return DomainUser::reconstitute(
            UserId::fromString($entity->getId()),
            $entity->getEmail(),
            $entity->getPasswordHash(),
            $entity->getCreatedAt(),
            $entity->getStatus(),
            $entity->getEmailVerifiedAt(),
            $entity->getType(),
        );
    }

    private function newEntityForType(UserType $type): User
    {
        return match ($type) {
            UserType::CLINIC     => new ClinicUser(),
            UserType::PORTAL     => new PortalUser(),
            UserType::BACKOFFICE => new BackofficeUser(),
        };
    }
}
