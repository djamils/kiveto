<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/**
 * The clinic changed the name its SMS come from or its SMS overage cap.
 */
readonly class ClinicMessagingSettingsChanged extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'clinic';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $clinicId,
        private string $smsSender,
        private int $overageCapSegments,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->clinicId;
    }

    /** @return array<string, int|string> */
    public function payload(): array
    {
        return [
            'clinicId'           => $this->clinicId,
            'smsSender'          => $this->smsSender,
            'overageCapSegments' => $this->overageCapSegments,
        ];
    }
}
