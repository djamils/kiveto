<?php

declare(strict_types=1);

namespace App\System\Audit\Domain;

use App\System\Audit\Domain\ValueObject\AuditAction;
use App\System\Audit\Domain\ValueObject\AuditActor;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\System\Audit\Domain\ValueObject\AuditDetail;
use App\System\Audit\Domain\ValueObject\AuditEntryId;

/**
 * One line of the audit log. The log is append-only: an entry is recorded
 * once and never changed nor removed, so it has no reconstitution and no
 * mutator — the read side answers views.
 */
final class AuditEntry
{
    private const int MAX_SUMMARY_LENGTH = 255;

    private const int MAX_CLINIC_NAME_LENGTH = 160;

    private AuditEntryId $id;
    private \DateTimeImmutable $occurredAt;
    private AuditActor $actor;
    private AuditAction $action;
    private AuditCategory $category;
    private string $summary;
    private ?string $clinicId;
    private ?string $clinicName;
    private ?string $targetUserId;
    private ?string $ipAddress;
    /** @var list<AuditDetail> */
    private array $details;

    private function __construct()
    {
    }

    /**
     * @param list<AuditDetail> $details
     */
    public static function record(
        AuditEntryId $id,
        \DateTimeImmutable $occurredAt,
        AuditActor $actor,
        AuditAction $action,
        AuditCategory $category,
        string $summary,
        ?string $clinicId = null,
        ?string $clinicName = null,
        ?string $targetUserId = null,
        ?string $ipAddress = null,
        array $details = [],
    ): self {
        $summary = trim($summary);

        if ('' === $summary) {
            throw new \InvalidArgumentException('An audit entry needs a summary.');
        }

        if (null !== $ipAddress && false === filter_var($ipAddress, \FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not an IP address.', $ipAddress));
        }

        $clinicName = null !== $clinicName ? trim($clinicName) : null;

        $entry               = new self();
        $entry->id           = $id;
        $entry->occurredAt   = $occurredAt;
        $entry->actor        = $actor;
        $entry->action       = $action;
        $entry->category     = $category;
        $entry->summary      = mb_substr($summary, 0, self::MAX_SUMMARY_LENGTH);
        $entry->clinicId     = $clinicId;
        $entry->clinicName   = '' === $clinicName || null === $clinicName ? null : mb_substr($clinicName, 0, self::MAX_CLINIC_NAME_LENGTH);
        $entry->targetUserId = $targetUserId;
        $entry->ipAddress    = $ipAddress;
        $entry->details      = $details;

        return $entry;
    }

    public function id(): AuditEntryId
    {
        return $this->id;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function actor(): AuditActor
    {
        return $this->actor;
    }

    public function action(): AuditAction
    {
        return $this->action;
    }

    public function category(): AuditCategory
    {
        return $this->category;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    public function clinicId(): ?string
    {
        return $this->clinicId;
    }

    public function clinicName(): ?string
    {
        return $this->clinicName;
    }

    public function targetUserId(): ?string
    {
        return $this->targetUserId;
    }

    public function ipAddress(): ?string
    {
        return $this->ipAddress;
    }

    /**
     * @return list<AuditDetail>
     */
    public function details(): array
    {
        return $this->details;
    }
}
