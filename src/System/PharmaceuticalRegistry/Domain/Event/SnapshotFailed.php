<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class SnapshotFailed extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'pharmaceutical_registry';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $snapshotId,
        public string $errorMessage,
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
            'snapshotId'   => $this->snapshotId,
            'errorMessage' => $this->errorMessage,
        ];
    }
}
