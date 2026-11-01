<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Mapper;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Domain\User as DomainUser;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Factory\DoctrineUserFactory;
use Symfony\Component\Uid\Uuid;

final readonly class UserMapper
{
    public function __construct(private DoctrineUserFactory $doctrineUserFactory)
    {
    }

    public function toEntity(DomainUser $domainUser): UserEntity
    {
        $entity = $this->doctrineUserFactory->createForType($domainUser->type());
        $entity->setId(Uuid::fromString($domainUser->id()->toString()));
        $entity->setEmail($domainUser->email());
        $entity->setPasswordHash($domainUser->passwordHash());
        $entity->setCreatedAt($domainUser->createdAt());
        $entity->setStatus($domainUser->status());
        $entity->setEmailVerifiedAt($domainUser->emailVerifiedAt());
        $entity->setPhone($domainUser->phone()?->toString());
        $entity->setPhoneVerifiedAt($domainUser->phoneVerifiedAt());
        $entity->setAnonymisedAt($domainUser->anonymisedAt());

        return $entity;
    }

    /** Copies what the lifecycle may change onto the loaded entity. */
    public function applyTo(UserEntity $entity, DomainUser $domainUser): void
    {
        $entity->setEmail($domainUser->email());
        $entity->setPasswordHash($domainUser->passwordHash());
        $entity->setStatus($domainUser->status());
        $entity->setEmailVerifiedAt($domainUser->emailVerifiedAt());
        $entity->setPhone($domainUser->phone()?->toString());
        $entity->setPhoneVerifiedAt($domainUser->phoneVerifiedAt());
        $entity->setAnonymisedAt($domainUser->anonymisedAt());
    }

    public function toDomain(UserEntity $entity): DomainUser
    {
        return DomainUser::reconstitute(
            UserId::fromString($entity->getId()->toRfc4122()),
            $entity->getEmail(),
            $entity->getPasswordHash(),
            $entity->getCreatedAt(),
            $entity->getStatus(),
            $entity->getEmailVerifiedAt(),
            $entity->getType(),
            null === $entity->getPhone() ? null : PhoneNumber::fromString($entity->getPhone()),
            $entity->getPhoneVerifiedAt(),
            $entity->getAnonymisedAt(),
        );
    }
}
