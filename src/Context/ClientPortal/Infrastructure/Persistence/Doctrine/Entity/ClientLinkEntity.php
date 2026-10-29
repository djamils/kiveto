<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the links between owner accounts and clinic clients; rows are
 * written and read in SQL by DbalClientLinkRepository.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_client_link', columns: ['user_id', 'clinic_id', 'client_id'])]
#[ORM\Index(name: 'idx_client_link_client', columns: ['clinic_id', 'client_id'])]
class ClientLinkEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $id,
        #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
        public Uuid $userId,
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        public Uuid $clinicId,
        #[ORM\Column(name: 'client_id', type: UuidType::NAME)]
        public Uuid $clientId,
        #[ORM\Column(type: 'string', length: 10)]
        public string $status,
        #[ORM\Column(name: 'linked_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $linkedAt,
        #[ORM\Column(name: 'revoked_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $revokedAt,
    ) {
    }
}
