<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the signed-in browsers of staff and back-office accounts;
 * written and read by DbalUserSessionStore.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_user_session_hash', columns: ['session_hash'])]
#[ORM\Index(name: 'idx_user_session_user', columns: ['user_id', 'last_seen_at'])]
class UserSessionEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $id,
        #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
        public Uuid $userId,
        #[ORM\Column(name: 'session_hash', type: 'string', length: 64, options: ['fixed' => true])]
        public string $sessionHash,
        #[ORM\Column(name: 'user_agent', type: 'string', length: 255, nullable: true)]
        public ?string $userAgent,
        #[ORM\Column(name: 'ip_address', type: 'string', length: 45, nullable: true)]
        public ?string $ipAddress,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'last_seen_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $lastSeenAt,
        #[ORM\Column(name: 'ended_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $endedAt,
        #[ORM\Column(name: 'revoked_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $revokedAt,
    ) {
    }
}
