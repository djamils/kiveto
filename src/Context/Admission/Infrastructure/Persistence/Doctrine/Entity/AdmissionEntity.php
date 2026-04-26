<?php

declare(strict_types=1);

namespace App\Context\Admission\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Admission\Domain\ValueObject\AdmissionStatus;
use App\Context\Admission\Domain\ValueObject\ClosureReason;
use App\Context\Admission\Domain\ValueObject\IntakeChannel;
use App\Context\Admission\Domain\ValueObject\LocationStatusValue;
use App\Context\Admission\Domain\ValueObject\PresenterRole;
use App\Context\Admission\Domain\ValueObject\TriageLevel;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_admission_clinic_status', columns: ['clinic_id', 'status'])]
#[ORM\Index(name: 'idx_admission_clinic_patient', columns: ['clinic_id', 'patient_id'])]
#[ORM\Index(name: 'idx_admission_waiting', columns: ['clinic_id', 'status', 'location_status_value'])]
#[ORM\UniqueConstraint(name: 'uniq_admission_appointment', columns: ['appointment_id'])]
class AdmissionEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(name: 'patient_id', type: UuidType::NAME)]
    private Uuid $patientId;

    #[ORM\Column(name: 'is_patient_identified_at_opening', type: 'boolean')]
    private bool $isPatientIdentifiedAtOpening;

    #[ORM\Column(name: 'intake_channel', type: 'string', length: 32, enumType: IntakeChannel::class)]
    private IntakeChannel $intakeChannel;

    #[ORM\Column(name: 'triage_level', type: 'string', length: 32, enumType: TriageLevel::class)]
    private TriageLevel $triageLevel;

    #[ORM\Column(name: 'presenter_name', type: 'string', length: 255, nullable: true)]
    private ?string $presenterName = null;

    #[ORM\Column(name: 'presenter_phone', type: 'string', length: 32, nullable: true)]
    private ?string $presenterPhone = null;

    #[ORM\Column(name: 'presenter_role', type: 'string', length: 32, nullable: true, enumType: PresenterRole::class)]
    private ?PresenterRole $presenterRole = null;

    #[ORM\Column(name: 'location_status_value', type: 'string', length: 32, enumType: LocationStatusValue::class)]
    private LocationStatusValue $locationStatusValue;

    #[ORM\Column(name: 'location_status_entered_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $locationStatusEnteredAt;

    #[ORM\Column(type: 'string', length: 16, enumType: AdmissionStatus::class)]
    private AdmissionStatus $status;

    #[ORM\Column(name: 'closure_reason', type: 'string', length: 32, nullable: true, enumType: ClosureReason::class)]
    private ?ClosureReason $closureReason = null;

    #[ORM\Column(name: 'appointment_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $appointmentId = null;

    #[ORM\Column(name: 'physical_description', type: 'text', nullable: true)]
    private ?string $physicalDescription = null;

    #[ORM\Column(name: 'triage_notes', type: 'text', nullable: true)]
    private ?string $triageNotes = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(name: 'opened_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $openedAt;

    #[ORM\Column(name: 'closed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getClinicId(): Uuid
    {
        return $this->clinicId;
    }

    public function setClinicId(Uuid $clinicId): void
    {
        $this->clinicId = $clinicId;
    }

    public function getPatientId(): Uuid
    {
        return $this->patientId;
    }

    public function setPatientId(Uuid $patientId): void
    {
        $this->patientId = $patientId;
    }

    public function isPatientIdentifiedAtOpening(): bool
    {
        return $this->isPatientIdentifiedAtOpening;
    }

    public function setIsPatientIdentifiedAtOpening(bool $isPatientIdentifiedAtOpening): void
    {
        $this->isPatientIdentifiedAtOpening = $isPatientIdentifiedAtOpening;
    }

    public function getIntakeChannel(): IntakeChannel
    {
        return $this->intakeChannel;
    }

    public function setIntakeChannel(IntakeChannel $intakeChannel): void
    {
        $this->intakeChannel = $intakeChannel;
    }

    public function getTriageLevel(): TriageLevel
    {
        return $this->triageLevel;
    }

    public function setTriageLevel(TriageLevel $triageLevel): void
    {
        $this->triageLevel = $triageLevel;
    }

    public function getPresenterName(): ?string
    {
        return $this->presenterName;
    }

    public function setPresenterName(?string $presenterName): void
    {
        $this->presenterName = $presenterName;
    }

    public function getPresenterPhone(): ?string
    {
        return $this->presenterPhone;
    }

    public function setPresenterPhone(?string $presenterPhone): void
    {
        $this->presenterPhone = $presenterPhone;
    }

    public function getPresenterRole(): ?PresenterRole
    {
        return $this->presenterRole;
    }

    public function setPresenterRole(?PresenterRole $presenterRole): void
    {
        $this->presenterRole = $presenterRole;
    }

    public function getLocationStatusValue(): LocationStatusValue
    {
        return $this->locationStatusValue;
    }

    public function setLocationStatusValue(LocationStatusValue $locationStatusValue): void
    {
        $this->locationStatusValue = $locationStatusValue;
    }

    public function getLocationStatusEnteredAt(): \DateTimeImmutable
    {
        return $this->locationStatusEnteredAt;
    }

    public function setLocationStatusEnteredAt(\DateTimeImmutable $locationStatusEnteredAt): void
    {
        $this->locationStatusEnteredAt = $locationStatusEnteredAt;
    }

    public function getStatus(): AdmissionStatus
    {
        return $this->status;
    }

    public function setStatus(AdmissionStatus $status): void
    {
        $this->status = $status;
    }

    public function getClosureReason(): ?ClosureReason
    {
        return $this->closureReason;
    }

    public function setClosureReason(?ClosureReason $closureReason): void
    {
        $this->closureReason = $closureReason;
    }

    public function getAppointmentId(): ?Uuid
    {
        return $this->appointmentId;
    }

    public function setAppointmentId(?Uuid $appointmentId): void
    {
        $this->appointmentId = $appointmentId;
    }

    public function getPhysicalDescription(): ?string
    {
        return $this->physicalDescription;
    }

    public function setPhysicalDescription(?string $physicalDescription): void
    {
        $this->physicalDescription = $physicalDescription;
    }

    public function getTriageNotes(): ?string
    {
        return $this->triageNotes;
    }

    public function setTriageNotes(?string $triageNotes): void
    {
        $this->triageNotes = $triageNotes;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getOpenedAt(): \DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function setOpenedAt(\DateTimeImmutable $openedAt): void
    {
        $this->openedAt = $openedAt;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeImmutable $closedAt): void
    {
        $this->closedAt = $closedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
