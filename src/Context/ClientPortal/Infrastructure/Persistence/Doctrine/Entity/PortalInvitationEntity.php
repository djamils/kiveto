<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the Portal invitations; rows are written and read in SQL by
 * DbalPortalInvitationRepository.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_portal_invitation_token', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_portal_invitation_phone', columns: ['phone', 'status'])]
#[ORM\Index(name: 'idx_portal_invitation_client', columns: ['clinic_id', 'client_id', 'status'])]
class PortalInvitationEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $id,
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        public Uuid $clinicId,
        #[ORM\Column(name: 'client_id', type: UuidType::NAME)]
        public Uuid $clientId,
        #[ORM\Column(type: 'string', length: 16)]
        public string $phone,
        #[ORM\Column(type: 'string', length: 255, nullable: true)]
        public ?string $email,
        #[ORM\Column(type: 'string', length: 10)]
        public string $channel,
        #[ORM\Column(name: 'token_hash', type: 'string', length: 64, options: ['fixed' => true])]
        public string $tokenHash,
        #[ORM\Column(type: 'string', length: 10)]
        public string $status,
        #[ORM\Column(name: 'sent_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $sentAt,
        #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $expiresAt,
        #[ORM\Column(name: 'accepted_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $acceptedAt,
        #[ORM\Column(name: 'accepted_by_user_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $acceptedByUserId,
        #[ORM\Column(name: 'revoked_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $revokedAt,
    ) {
    }
}
