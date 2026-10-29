<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the account tokens; rows are written and read in SQL by
 * DbalAccountTokenRepository.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_account_token_hash', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_account_token_user', columns: ['user_id', 'purpose', 'clinic_id'])]
class AccountTokenEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $id,
        #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
        public Uuid $userId,
        #[ORM\Column(type: 'string', length: 20)]
        public string $purpose,
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $clinicId,
        #[ORM\Column(name: 'token_hash', type: 'string', length: 64, options: ['fixed' => true])]
        public string $tokenHash,
        #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $expiresAt,
        #[ORM\Column(name: 'used_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $usedAt,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
