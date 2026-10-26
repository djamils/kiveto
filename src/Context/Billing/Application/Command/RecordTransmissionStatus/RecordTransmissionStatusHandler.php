<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RecordTransmissionStatus;

use App\Context\Billing\Application\Port\TransmissionStatusProjectionInterface;
use App\Context\Billing\Application\Port\TransmissionStatusUpdate;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Keeps the newest report only: a duplicate or a late, older one changes nothing.
 */
#[AsMessageHandler]
final readonly class RecordTransmissionStatusHandler
{
    public function __construct(private TransmissionStatusProjectionInterface $projection)
    {
    }

    public function __invoke(RecordTransmissionStatus $command): bool
    {
        return $this->projection->apply(new TransmissionStatusUpdate(
            documentId: $command->documentId,
            sequence: $command->sequence,
            state: $command->state,
            blockReason: $command->blockReason,
            statusCode: $command->statusCode,
            statusLabel: $command->statusLabel,
            reasonCode: $command->reasonCode,
            reasonText: $command->reasonText,
            requiresAction: $command->requiresAction,
            updatedAt: new \DateTimeImmutable($command->occurredAt),
        ));
    }
}
