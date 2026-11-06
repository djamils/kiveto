<?php

declare(strict_types=1);

namespace App\System\Audit\Application\Port;

use App\System\Audit\Domain\ValueObject\ActorKind;
use App\System\Audit\Domain\ValueObject\AuditCategory;

/**
 * The filters of the audit log; null means « every ». `userId` keeps what an
 * account wrote and what targets it; `text` matches the summary, the action
 * code, the actor's and the clinic's names.
 */
final readonly class AuditLogCriteria
{
    public function __construct(
        public ?AuditCategory $category = null,
        public ?string $clinicId = null,
        public ?ActorKind $actorKind = null,
        public ?string $userId = null,
        public ?\DateTimeImmutable $since = null,
        public ?string $text = null,
    ) {
    }

    /**
     * The filters of the page: a period of the last N days (null: since the
     * beginning), a search text ignored when blank.
     */
    public static function fromFilters(
        ?AuditCategory $category,
        ?string $clinicId,
        ?ActorKind $actorKind,
        ?string $userId,
        ?int $periodDays,
        ?string $text,
        \DateTimeImmutable $now,
    ): self {
        $text = null !== $text ? trim($text) : null;

        return new self(
            category: $category,
            clinicId: $clinicId,
            actorKind: $actorKind,
            userId: $userId,
            since: null !== $periodDays && $periodDays > 0 ? $now->modify(\sprintf('-%d days', $periodDays)) : null,
            text: '' === $text ? null : $text,
        );
    }
}
