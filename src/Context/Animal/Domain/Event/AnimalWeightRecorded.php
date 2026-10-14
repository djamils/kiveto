<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class AnimalWeightRecorded extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'animal';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $weightRecordId,
        private string $animalId,
        private string $clinicId,
        private int $weightGrams,
        private string $measuredOn,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->weightRecordId;
    }

    public function payload(): array
    {
        return [
            'weightRecordId' => $this->weightRecordId,
            'animalId'       => $this->animalId,
            'clinicId'       => $this->clinicId,
            'weightGrams'    => $this->weightGrams,
            'measuredOn'     => $this->measuredOn,
        ];
    }
}
