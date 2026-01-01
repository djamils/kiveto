<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence\Doctrine\Mapper;

use App\IdentityAccess\Domain\User as DomainUser;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Factory\DoctrineUserFactory;

final readonly class UserMapper
{
    public function __construct(private DoctrineUserFactory $doctrineUserFactory)
    {
    }

    public function toEntity(DomainUser $domainUser): User
    {
        $entity = $this->doctrineUserFactory->createForType($domainUser->type());
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
}
