<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\Event;

use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Context\Consultation\Domain\ValueObject\WaitingRoomEntryId;
use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class ConsultationStartedFromWaitingRoomEntry extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'consultation';
    protected const int VERSION            = 1;

    public function __construct(
        public ConsultationId $consultationId,
        public ClinicId $clinicId,
        public WaitingRoomEntryId $waitingRoomEntryId,
        public UserId $practitionerUserId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->consultationId->toString();
    }

    public function payload(): array
    {
        return [
            'consultationId'     => $this->consultationId->toString(),
            'clinicId'           => $this->clinicId->toString(),
            'waitingRoomEntryId' => $this->waitingRoomEntryId->toString(),
            'practitionerUserId' => $this->practitionerUserId->toString(),
            'occurredOn'         => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
