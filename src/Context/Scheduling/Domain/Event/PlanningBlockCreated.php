<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class PlanningBlockCreated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'scheduling';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $planningBlockId,
        private string $clinicId,
        private string $staffUserId,
        private string $type,
        private string $date,
        private string $startTime,
        private string $endTime,
        private int $capacityPerHour,
        private string $recurrenceFreq,
        private ?string $recurrenceUntil,
        private ?string $note,
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
            'staffUserId'     => $this->staffUserId,
            'type'            => $this->type,
            'date'            => $this->date,
            'startTime'       => $this->startTime,
            'endTime'         => $this->endTime,
            'capacityPerHour' => $this->capacityPerHour,
            'recurrenceFreq'  => $this->recurrenceFreq,
            'recurrenceUntil' => $this->recurrenceUntil,
            'note'            => $this->note,
        ];
    }
}
