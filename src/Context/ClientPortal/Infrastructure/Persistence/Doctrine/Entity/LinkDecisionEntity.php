<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the clinics' answers to « Rattachement » requests (S6-F3 audit
 * trail); rows are written and read in SQL by DbalLinkDecisionRepository.
 * `client_ids` lists the client files the answer decided.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_link_decision_clinic', columns: ['clinic_id', 'decided_at', 'id'])]
class LinkDecisionEntity
{
    /**
     * @param list<string> $clientIds
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $id,
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        public Uuid $clinicId,
        #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
        public Uuid $userId,
        #[ORM\Column(type: 'string', length: 10)]
        public string $outcome,
        #[ORM\Column(name: 'client_ids', type: 'json')]
        public array $clientIds,
        #[ORM\Column(name: 'decided_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $decidedAt,
        #[ORM\Column(name: 'decided_by_user_id', type: UuidType::NAME)]
        public Uuid $decidedByUserId,
        #[ORM\Column(name: 'decided_by_name', type: 'string', length: 255)]
        public string $decidedByName,
    ) {
    }
}
