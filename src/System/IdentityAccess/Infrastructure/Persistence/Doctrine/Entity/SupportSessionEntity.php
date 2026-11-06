<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the console's support sessions; read and written in SQL by
 * DbalSupportSessionRepository.
 */
#[ORM\Entity]
#[ORM\Table(name: 'identity_access__support_sessions')]
#[ORM\UniqueConstraint(name: 'uniq_support_session_handoff', columns: ['handoff_hash'])]
#[ORM\Index(name: 'idx_support_session_clinic', columns: ['clinic_id', 'started_at'])]
class SupportSessionEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $id,
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        public Uuid $clinicId,
        #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
        public Uuid $userId,
        #[ORM\Column(name: 'admin_id', type: UuidType::NAME)]
        public Uuid $adminId,
        #[ORM\Column(name: 'admin_name', type: 'string', length: 160)]
        public string $adminName,
        #[ORM\Column(type: 'string', length: 500)]
        public string $reason,
        #[ORM\Column(type: 'string', length: 8)]
        public string $mode,
        #[ORM\Column(name: 'started_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $startedAt,
        #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $expiresAt,
        #[ORM\Column(name: 'handoff_hash', type: 'string', length: 64)]
        public string $handoffHash,
        #[ORM\Column(name: 'claimed_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $claimedAt,
        #[ORM\Column(name: 'ended_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $endedAt,
        #[ORM\Column(type: 'integer')]
        public int $pages,
        #[ORM\Column(type: 'integer')]
        public int $writes,
    ) {
    }
}
