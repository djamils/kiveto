<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the Portal API refresh tokens; rows are written and read in SQL
 * by DbalRefreshTokenRepository. Only digests are stored.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_refresh_token_hash', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_refresh_token_family', columns: ['family_id'])]
#[ORM\Index(name: 'idx_refresh_token_user', columns: ['user_id'])]
class RefreshTokenEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $id,
        #[ORM\Column(name: 'family_id', type: UuidType::NAME)]
        public Uuid $familyId,
        #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
        public Uuid $userId,
        #[ORM\Column(name: 'token_hash', type: 'string', length: 64, options: ['fixed' => true])]
        public string $tokenHash,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'last_used_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $lastUsedAt,
        #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $expiresAt,
        #[ORM\Column(name: 'rotated_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $rotatedAt,
        #[ORM\Column(name: 'rotated_to_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $rotatedToId,
        #[ORM\Column(name: 'grace_successor_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $graceSuccessorId,
        #[ORM\Column(name: 'revoked_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $revokedAt,
    ) {
    }
}
