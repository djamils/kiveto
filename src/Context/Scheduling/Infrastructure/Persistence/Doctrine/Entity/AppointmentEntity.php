<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Context\Scheduling\Domain\ValueObject\CancellationReason;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_clinic_starts', columns: ['clinic_id', 'starts_at_utc'])]
#[ORM\Index(name: 'idx_clinic_practitioner_starts', columns: ['clinic_id', 'practitioner_user_id', 'starts_at_utc'])]
#[ORM\Index(name: 'idx_status', columns: ['status'])]
#[ORM\Index(name: 'idx_status_pending_expires', columns: ['status', 'pending_expires_at'])]
class AppointmentEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(name: 'linked_admission_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $linkedAdmissionId;

    #[ORM\Column(name: 'owner_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $ownerId = null;

    #[ORM\Column(name: 'animal_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $animalId = null;

    #[ORM\Column(name: 'practitioner_user_id', type: UuidType::NAME)]
    private Uuid $practitionerUserId;

    #[ORM\Column(name: 'starts_at_utc', type: 'datetime_immutable')]
    private \DateTimeImmutable $startsAtUtc;

    #[ORM\Column(name: 'duration_minutes', type: 'integer')]
    private int $durationMinutes;

    #[ORM\Column(type: 'string', length: 32, enumType: AppointmentStatus::class)]
    private AppointmentStatus $status;

    #[ORM\Column(type: 'string', length: 16, enumType: AppointmentSource::class, options: ['default' => 'clinic'])]
    private AppointmentSource $source = AppointmentSource::CLINIC;

    #[ORM\Column(name: 'booking_motif_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $bookingMotifId = null;

    #[ORM\Column(name: 'cancelled_by', type: 'string', length: 16, nullable: true, enumType: CancellationActor::class)]
    private ?CancellationActor $cancelledBy = null;

    #[ORM\Column(name: 'cancellation_reason', type: 'string', length: 32, nullable: true, enumType: CancellationReason::class)]
    private ?CancellationReason $cancellationReason = null;

    #[ORM\Column(name: 'pending_expires_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $pendingExpiresAt = null;

    #[ORM\Column(name: 'confirmed_silently', type: 'boolean', options: ['default' => false])]
    private bool $confirmedSilently = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes;

    #[ORM\Column(name: 'service_started_at_utc', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $serviceStartedAt;

    #[ORM\Version]
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(name: 'created_at_utc', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at_utc', type: 'datetime_immutable')]
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

    public function getLinkedAdmissionId(): ?Uuid
    {
        return $this->linkedAdmissionId;
    }

    public function setLinkedAdmissionId(?Uuid $linkedAdmissionId): void
    {
        $this->linkedAdmissionId = $linkedAdmissionId;
    }

    public function getOwnerId(): ?Uuid
    {
        return $this->ownerId;
    }

    public function setOwnerId(?Uuid $ownerId): void
    {
        $this->ownerId = $ownerId;
    }

    public function getAnimalId(): ?Uuid
    {
        return $this->animalId;
    }

    public function setAnimalId(?Uuid $animalId): void
    {
        $this->animalId = $animalId;
    }

    public function getPractitionerUserId(): Uuid
    {
        return $this->practitionerUserId;
    }

    public function setPractitionerUserId(Uuid $practitionerUserId): void
    {
        $this->practitionerUserId = $practitionerUserId;
    }

    public function getStartsAtUtc(): \DateTimeImmutable
    {
        return $this->startsAtUtc;
    }

    public function setStartsAtUtc(\DateTimeImmutable $startsAtUtc): void
    {
        $this->startsAtUtc = $startsAtUtc;
    }

    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $durationMinutes): void
    {
        $this->durationMinutes = $durationMinutes;
    }

    public function getStatus(): AppointmentStatus
    {
        return $this->status;
    }

    public function setStatus(AppointmentStatus $status): void
    {
        $this->status = $status;
    }

    public function getSource(): AppointmentSource
    {
        return $this->source;
    }

    public function setSource(AppointmentSource $source): void
    {
        $this->source = $source;
    }

    public function getBookingMotifId(): ?Uuid
    {
        return $this->bookingMotifId;
    }

    public function setBookingMotifId(?Uuid $bookingMotifId): void
    {
        $this->bookingMotifId = $bookingMotifId;
    }

    public function getCancelledBy(): ?CancellationActor
    {
        return $this->cancelledBy;
    }

    public function setCancelledBy(?CancellationActor $cancelledBy): void
    {
        $this->cancelledBy = $cancelledBy;
    }

    public function getCancellationReason(): ?CancellationReason
    {
        return $this->cancellationReason;
    }

    public function setCancellationReason(?CancellationReason $cancellationReason): void
    {
        $this->cancellationReason = $cancellationReason;
    }

    public function getPendingExpiresAt(): ?\DateTimeImmutable
    {
        return $this->pendingExpiresAt;
    }

    public function setPendingExpiresAt(?\DateTimeImmutable $pendingExpiresAt): void
    {
        $this->pendingExpiresAt = $pendingExpiresAt;
    }

    public function isConfirmedSilently(): bool
    {
        return $this->confirmedSilently;
    }

    public function setConfirmedSilently(bool $confirmedSilently): void
    {
        $this->confirmedSilently = $confirmedSilently;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): void
    {
        $this->reason = $reason;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getServiceStartedAt(): ?\DateTimeImmutable
    {
        return $this->serviceStartedAt;
    }

    public function setServiceStartedAt(?\DateTimeImmutable $serviceStartedAt): void
    {
        $this->serviceStartedAt = $serviceStartedAt;
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

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
