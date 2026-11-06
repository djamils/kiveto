<?php

declare(strict_types=1);

namespace App\System\Audit\Application\Query;

/**
 * One line of the audit log as it was written.
 */
final readonly class AuditEntryView
{
    /**
     * @param list<AuditDetailView> $details
     */
    public function __construct(
        public string $id,
        public \DateTimeImmutable $occurredAt,
        public string $actorKind,
        public ?string $actorId,
        public ?string $actorName,
        public string $action,
        public string $category,
        public string $summary,
        public ?string $clinicId,
        public ?string $clinicName,
        public ?string $targetUserId,
        public ?string $ipAddress,
        public array $details,
    ) {
    }
}
