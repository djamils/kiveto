<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class PreventiveCareRecorded extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'animal';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $preventiveCareRecordId,
        private string $animalId,
        private string $clinicId,
        private string $kind,
        private string $label,
        private string $administeredOn,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->preventiveCareRecordId;
    }

    public function payload(): array
    {
        return [
            'preventiveCareRecordId' => $this->preventiveCareRecordId,
            'animalId'               => $this->animalId,
            'clinicId'               => $this->clinicId,
            'kind'                   => $this->kind,
            'label'                  => $this->label,
            'administeredOn'         => $this->administeredOn,
        ];
    }
}
