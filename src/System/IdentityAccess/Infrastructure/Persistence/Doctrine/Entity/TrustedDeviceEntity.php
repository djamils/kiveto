<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the trusted browsers; written and read by DbalTrustedDeviceRepository.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_trusted_device_token', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_trusted_device_user', columns: ['user_id', 'expires_at'])]
class TrustedDeviceEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $id,
        #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
        public Uuid $userId,
        #[ORM\Column(name: 'token_hash', type: 'string', length: 64, options: ['fixed' => true])]
        public string $tokenHash,
        #[ORM\Column(type: 'string', length: 120)]
        public string $label,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $expiresAt,
        #[ORM\Column(name: 'revoked_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $revokedAt,
    ) {
    }
}
