<?php

declare(strict_types=1);

namespace App\System\Audit\Application\Command\RecordAuditEntry;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Audit\Domain\AuditEntry;
use App\System\Audit\Domain\Repository\AuditEntryRepositoryInterface;
use App\System\Audit\Domain\ValueObject\ActorKind;
use App\System\Audit\Domain\ValueObject\AuditAction;
use App\System\Audit\Domain\ValueObject\AuditActor;
use App\System\Audit\Domain\ValueObject\AuditDetail;
use App\System\Audit\Domain\ValueObject\AuditEntryId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordAuditEntryHandler
{
    public function __construct(
        private AuditEntryRepositoryInterface $entries,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordAuditEntry $command): string
    {
        $id = AuditEntryId::fromString($this->uuidGenerator->generate());

        $this->entries->append(AuditEntry::record(
            id: $id,
            occurredAt: $command->occurredAt ?? $this->clock->now(),
            actor: $this->actor($command),
            action: AuditAction::fromString($command->action),
            category: $command->category,
            summary: $command->summary,
            clinicId: $command->clinicId,
            clinicName: $command->clinicName,
            targetUserId: $command->targetUserId,
            ipAddress: $command->ipAddress,
            details: array_map(
                static fn (array $detail): AuditDetail => AuditDetail::of($detail['label'], $detail['value']),
                $command->details,
            ),
        ));

        return $id->toString();
    }

    private function actor(RecordAuditEntry $command): AuditActor
    {
        return match ($command->actorKind) {
            ActorKind::KIVETO  => AuditActor::kiveto((string) $command->actorId, (string) $command->actorName),
            ActorKind::STAFF   => AuditActor::staff((string) $command->actorId, (string) $command->actorName),
            ActorKind::SYSTEM  => AuditActor::system((string) $command->actorName),
            ActorKind::UNKNOWN => AuditActor::unknown(),
        };
    }
}
