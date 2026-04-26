<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\Event;

use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\AppointmentId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class ConsultationStartedFromAppointment extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'consultation';
    protected const int VERSION            = 1;

    public function __construct(
        public ConsultationId $consultationId,
        public ClinicId $clinicId,
        public AppointmentId $appointmentId,
        public AdmissionId $admissionId,
        public PatientId $patientId,
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
            'appointmentId'      => $this->appointmentId->toString(),
            'admissionId'        => $this->admissionId->toString(),
            'patientId'          => $this->patientId->toString(),
            'practitionerUserId' => $this->practitionerUserId->toString(),
            'occurredOn'         => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
