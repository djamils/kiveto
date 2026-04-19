<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class PlanningBlockDeleted extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'scheduling';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $planningBlockId,
        private string $clinicId,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->planningBlockId;
    }

    public function payload(): array
    {
        return [
            'planningBlockId' => $this->planningBlockId,
            'clinicId'        => $this->clinicId,
        ];
    }
}
