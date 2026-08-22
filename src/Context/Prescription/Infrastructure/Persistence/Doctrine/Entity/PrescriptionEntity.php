<?php

declare(strict_types=1);

namespace App\Context\Prescription\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Prescription\Domain\ValueObject\PrescriptionStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_prescription_clinic_status', columns: ['clinic_id', 'status'])]
#[ORM\Index(name: 'idx_prescription_clinic_issued', columns: ['clinic_id', 'issued_at'])]
#[ORM\Index(name: 'idx_prescription_consultation', columns: ['consultation_id'])]
#[ORM\UniqueConstraint(name: 'uniq_prescription_number', columns: ['clinic_id', 'number'])]
class PrescriptionEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(name: 'consultation_id', type: UuidType::NAME)]
    private Uuid $consultationId;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $number = null;

    #[ORM\Column(type: 'string', length: 20, enumType: PrescriptionStatus::class)]
    private PrescriptionStatus $status;

    #[ORM\Column(name: 'prescriber_user_id', type: UuidType::NAME)]
    private Uuid $prescriberUserId;

    #[ORM\Column(name: 'prescriber_display_name', type: 'string', length: 255)]
    private string $prescriberDisplayName;

    #[ORM\Column(name: 'prescriber_registration_number', type: 'string', length: 64, nullable: true)]
    private ?string $prescriberRegistrationNumber = null;

    #[ORM\Column(name: 'prescriber_professional_title', type: 'string', length: 32, nullable: true)]
    private ?string $prescriberProfessionalTitle = null;

    #[ORM\Column(name: 'patient_animal_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $patientAnimalId = null;

    #[ORM\Column(name: 'patient_name', type: 'string', length: 255)]
    private string $patientName;

    #[ORM\Column(name: 'patient_species', type: 'string', length: 32, nullable: true)]
    private ?string $patientSpecies = null;

    #[ORM\Column(name: 'patient_breed', type: 'string', length: 120, nullable: true)]
    private ?string $patientBreed = null;

    #[ORM\Column(name: 'patient_birth_date', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $patientBirthDate = null;

    #[ORM\Column(name: 'patient_weight_kg', type: 'decimal', precision: 6, scale: 3, nullable: true)]
    private ?string $patientWeightKg = null;

    #[ORM\Column(name: 'patient_microchip_number', type: 'string', length: 32, nullable: true)]
    private ?string $patientMicrochipNumber = null;

    #[ORM\Column(name: 'owner_client_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $ownerClientId = null;

    #[ORM\Column(name: 'owner_name', type: 'string', length: 255)]
    private string $ownerName;

    /** @var list<string> */
    #[ORM\Column(name: 'owner_address_lines', type: 'json')]
    private array $ownerAddressLines = [];

    #[ORM\Column(name: 'issued_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $issuedAt = null;

    #[ORM\Column(name: 'valid_until', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;

    #[ORM\Column(name: 'validity_span_days', type: 'smallint', nullable: true)]
    private ?int $validitySpanDays = null;

    #[ORM\Column(name: 'renewals_allowed', type: 'smallint')]
    private int $renewalsAllowed = 0;

    #[ORM\Column(name: 'renewals_used', type: 'smallint')]
    private int $renewalsUsed = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'cancelled_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(name: 'cancellation_reason', type: 'string', length: 255, nullable: true)]
    private ?string $cancellationReason = null;

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

    public function getConsultationId(): Uuid
    {
        return $this->consultationId;
    }

    public function setConsultationId(Uuid $consultationId): void
    {
        $this->consultationId = $consultationId;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): void
    {
        $this->number = $number;
    }

    public function getStatus(): PrescriptionStatus
    {
        return $this->status;
    }

    public function setStatus(PrescriptionStatus $status): void
    {
        $this->status = $status;
    }

    public function getPrescriberUserId(): Uuid
    {
        return $this->prescriberUserId;
    }

    public function setPrescriberUserId(Uuid $prescriberUserId): void
    {
        $this->prescriberUserId = $prescriberUserId;
    }

    public function getPrescriberDisplayName(): string
    {
        return $this->prescriberDisplayName;
    }

    public function setPrescriberDisplayName(string $prescriberDisplayName): void
    {
        $this->prescriberDisplayName = $prescriberDisplayName;
    }

    public function getPrescriberRegistrationNumber(): ?string
    {
        return $this->prescriberRegistrationNumber;
    }

    public function setPrescriberRegistrationNumber(?string $prescriberRegistrationNumber): void
    {
        $this->prescriberRegistrationNumber = $prescriberRegistrationNumber;
    }

    public function getPrescriberProfessionalTitle(): ?string
    {
        return $this->prescriberProfessionalTitle;
    }

    public function setPrescriberProfessionalTitle(?string $prescriberProfessionalTitle): void
    {
        $this->prescriberProfessionalTitle = $prescriberProfessionalTitle;
    }

    public function getPatientAnimalId(): ?Uuid
    {
        return $this->patientAnimalId;
    }

    public function setPatientAnimalId(?Uuid $patientAnimalId): void
    {
        $this->patientAnimalId = $patientAnimalId;
    }

    public function getPatientName(): string
    {
        return $this->patientName;
    }

    public function setPatientName(string $patientName): void
    {
        $this->patientName = $patientName;
    }

    public function getPatientSpecies(): ?string
    {
        return $this->patientSpecies;
    }

    public function setPatientSpecies(?string $patientSpecies): void
    {
        $this->patientSpecies = $patientSpecies;
    }

    public function getPatientBreed(): ?string
    {
        return $this->patientBreed;
    }

    public function setPatientBreed(?string $patientBreed): void
    {
        $this->patientBreed = $patientBreed;
    }

    public function getPatientBirthDate(): ?\DateTimeImmutable
    {
        return $this->patientBirthDate;
    }

    public function setPatientBirthDate(?\DateTimeImmutable $patientBirthDate): void
    {
        $this->patientBirthDate = $patientBirthDate;
    }

    public function getPatientWeightKg(): ?string
    {
        return $this->patientWeightKg;
    }

    public function setPatientWeightKg(?string $patientWeightKg): void
    {
        $this->patientWeightKg = $patientWeightKg;
    }

    public function getPatientMicrochipNumber(): ?string
    {
        return $this->patientMicrochipNumber;
    }

    public function setPatientMicrochipNumber(?string $patientMicrochipNumber): void
    {
        $this->patientMicrochipNumber = $patientMicrochipNumber;
    }

    public function getOwnerClientId(): ?Uuid
    {
        return $this->ownerClientId;
    }

    public function setOwnerClientId(?Uuid $ownerClientId): void
    {
        $this->ownerClientId = $ownerClientId;
    }

    public function getOwnerName(): string
    {
        return $this->ownerName;
    }

    public function setOwnerName(string $ownerName): void
    {
        $this->ownerName = $ownerName;
    }

    /**
     * @return list<string>
     */
    public function getOwnerAddressLines(): array
    {
        return $this->ownerAddressLines;
    }

    /**
     * @param list<string> $ownerAddressLines
     */
    public function setOwnerAddressLines(array $ownerAddressLines): void
    {
        $this->ownerAddressLines = $ownerAddressLines;
    }

    public function getIssuedAt(): ?\DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(?\DateTimeImmutable $issuedAt): void
    {
        $this->issuedAt = $issuedAt;
    }

    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeImmutable $validUntil): void
    {
        $this->validUntil = $validUntil;
    }

    public function getValiditySpanDays(): ?int
    {
        return $this->validitySpanDays;
    }

    public function setValiditySpanDays(?int $validitySpanDays): void
    {
        $this->validitySpanDays = $validitySpanDays;
    }

    public function getRenewalsAllowed(): int
    {
        return $this->renewalsAllowed;
    }

    public function setRenewalsAllowed(int $renewalsAllowed): void
    {
        $this->renewalsAllowed = $renewalsAllowed;
    }

    public function getRenewalsUsed(): int
    {
        return $this->renewalsUsed;
    }

    public function setRenewalsUsed(int $renewalsUsed): void
    {
        $this->renewalsUsed = $renewalsUsed;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
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

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): void
    {
        $this->cancelledAt = $cancelledAt;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function setCancellationReason(?string $cancellationReason): void
    {
        $this->cancellationReason = $cancellationReason;
    }
}
