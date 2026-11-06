<?php

declare(strict_types=1);

namespace App\System\Audit\Application\Command\RecordAuditEntry;

use App\Shared\Application\Bus\CommandInterface;
use App\System\Audit\Domain\ValueObject\ActorKind;
use App\System\Audit\Domain\ValueObject\AuditCategory;

/**
 * Appends one line to the audit log. The caller knows who acted and what it
 * meant: it gives the actor's printed name of the moment and a French
 * summary. `occurredAt` defaults to the clock's time.
 */
final readonly class RecordAuditEntry implements CommandInterface
{
    /**
     * @param list<array{label: string, value: string}> $details
     */
    public function __construct(
        public ActorKind $actorKind,
        public ?string $actorId,
        public ?string $actorName,
        public string $action,
        public AuditCategory $category,
        public string $summary,
        public ?string $clinicId = null,
        public ?string $clinicName = null,
        public ?string $targetUserId = null,
        public ?string $ipAddress = null,
        public array $details = [],
        public ?\DateTimeImmutable $occurredAt = null,
    ) {
    }
}
