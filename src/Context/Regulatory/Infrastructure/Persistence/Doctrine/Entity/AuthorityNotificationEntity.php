<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Regulatory\Domain\ValueObject\AuthorityNotificationStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'authority_notifications')]
class AuthorityNotificationEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'admission_id', type: UuidType::NAME)]
    private Uuid $admissionId;

    #[ORM\Column(name: 'patient_id', type: UuidType::NAME)]
    private Uuid $patientId;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(type: 'string', length: 16, enumType: AuthorityNotificationStatus::class)]
    private AuthorityNotificationStatus $status;

    #[ORM\Column(name: 'deadline', type: 'datetime_immutable')]
    private \DateTimeImmutable $deadline;

    #[ORM\Version]
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

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

    public function getAdmissionId(): Uuid
    {
        return $this->admissionId;
    }

    public function setAdmissionId(Uuid $admissionId): void
    {
        $this->admissionId = $admissionId;
    }

    public function getPatientId(): Uuid
    {
        return $this->patientId;
    }

    public function setPatientId(Uuid $patientId): void
    {
        $this->patientId = $patientId;
    }

    public function getClinicId(): Uuid
    {
        return $this->clinicId;
    }

    public function setClinicId(Uuid $clinicId): void
    {
        $this->clinicId = $clinicId;
    }

    public function getStatus(): AuthorityNotificationStatus
    {
        return $this->status;
    }

    public function setStatus(AuthorityNotificationStatus $status): void
    {
        $this->status = $status;
    }

    public function getDeadline(): \DateTimeImmutable
    {
        return $this->deadline;
    }

    public function setDeadline(\DateTimeImmutable $deadline): void
    {
        $this->deadline = $deadline;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
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
