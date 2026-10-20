<?php

declare(strict_types=1);

namespace App\Tests\Support\Billing;

use App\Context\Billing\Application\Port\TransmissionStatusProjectionInterface;
use App\Context\Billing\Application\Port\TransmissionStatusUpdate;

/**
 * Records what the bridge asks of the projection.
 */
final class InMemoryTransmissionStatusProjection implements TransmissionStatusProjectionInterface
{
    /** @var list<array{string, string}> */
    public array $requested = [];

    /** @var list<array{string, string}> */
    public array $failed = [];

    /** @var list<TransmissionStatusUpdate> */
    public array $applied = [];

    /** @var list<array{clinicId: string, documentId: string, kind: string, issuedAt: \DateTimeImmutable}> */
    public array $waiting = [];

    public ?\DateTimeImmutable $askedBefore = null;

    public function markRequested(string $documentId, string $kind, \DateTimeImmutable $now): void
    {
        $this->requested[] = [$documentId, $kind];
    }

    public function markRequestFailed(string $documentId, string $kind, \DateTimeImmutable $now): void
    {
        $this->failed[] = [$documentId, $kind];
    }

    public function apply(TransmissionStatusUpdate $update): bool
    {
        $this->applied[] = $update;

        return true;
    }

    public function stillRequested(\DateTimeImmutable $issuedBefore): array
    {
        $this->askedBefore = $issuedBefore;

        return $this->waiting;
    }
}
