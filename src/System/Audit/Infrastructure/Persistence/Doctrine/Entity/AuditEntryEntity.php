<?php

declare(strict_types=1);

namespace App\System\Audit\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the audit log; rows are inserted and read in SQL by the DBAL
 * repositories, never updated nor deleted.
 */
#[ORM\Entity]
#[ORM\Table(name: 'audit__entries')]
#[ORM\Index(name: 'idx_audit_entry_occurred', columns: ['occurred_at'])]
#[ORM\Index(name: 'idx_audit_entry_clinic', columns: ['clinic_id', 'occurred_at'])]
#[ORM\Index(name: 'idx_audit_entry_target', columns: ['target_user_id', 'occurred_at'])]
#[ORM\Index(name: 'idx_audit_entry_actor', columns: ['actor_id', 'occurred_at'])]
#[ORM\Index(name: 'idx_audit_entry_category', columns: ['category', 'occurred_at'])]
class AuditEntryEntity
{
    /**
     * @param list<array{label: string, value: string}> $details
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $id,
        #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $occurredAt,
        #[ORM\Column(name: 'actor_kind', type: 'string', length: 10)]
        public string $actorKind,
        #[ORM\Column(name: 'actor_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $actorId,
        #[ORM\Column(name: 'actor_name', type: 'string', length: 160, nullable: true)]
        public ?string $actorName,
        #[ORM\Column(type: 'string', length: 64)]
        public string $action,
        #[ORM\Column(type: 'string', length: 16)]
        public string $category,
        #[ORM\Column(type: 'string', length: 255)]
        public string $summary,
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $clinicId,
        #[ORM\Column(name: 'clinic_name', type: 'string', length: 160, nullable: true)]
        public ?string $clinicName,
        #[ORM\Column(name: 'target_user_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $targetUserId,
        #[ORM\Column(name: 'ip_address', type: 'string', length: 45, nullable: true)]
        public ?string $ipAddress,
        #[ORM\Column(type: 'json')]
        public array $details,
    ) {
    }
}
