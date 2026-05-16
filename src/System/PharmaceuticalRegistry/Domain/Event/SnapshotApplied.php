<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class SnapshotApplied extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'pharmaceutical_registry';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $snapshotId,
        public int $created,
        public int $updated,
        public int $withdrawn,
        public int $skipped,
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
            'snapshotId' => $this->snapshotId,
            'created'    => $this->created,
            'updated'    => $this->updated,
            'withdrawn'  => $this->withdrawn,
            'skipped'    => $this->skipped,
        ];
    }
}
