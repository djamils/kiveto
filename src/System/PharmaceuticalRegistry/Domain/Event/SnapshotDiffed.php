<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class SnapshotDiffed extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'pharmaceutical_registry';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $snapshotId,
        public int $createCount,
        public int $updateCount,
        public int $withdrawCount,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->snapshotId;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'snapshotId'    => $this->snapshotId,
            'createCount'   => $this->createCount,
            'updateCount'   => $this->updateCount,
            'withdrawCount' => $this->withdrawCount,
        ];
    }
}
