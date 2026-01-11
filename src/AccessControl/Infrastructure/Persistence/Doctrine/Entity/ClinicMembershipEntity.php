<?php

declare(strict_types=1);

namespace App\AccessControl\Infrastructure\Persistence\Doctrine\Entity;

use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\ClinicMembershipStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_clinic_user', columns: ['clinic_id', 'user_id'])]
#[ORM\Index(name: 'idx_user_id', columns: ['user_id'])]
#[ORM\Index(name: 'idx_clinic_id', columns: ['clinic_id'])]
#[ORM\Index(name: 'idx_status', columns: ['status'])]
class ClinicMembershipEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
    private Uuid $userId;

    #[ORM\Column(type: 'string', length: 40, enumType: ClinicMemberRole::class)]
    private ClinicMemberRole $role;

    #[ORM\Column(type: 'string', length: 20, enumType: ClinicMembershipEngagement::class)]
    private ClinicMembershipEngagement $engagement;

    #[ORM\Column(type: 'string', length: 20, enumType: ClinicMembershipStatus::class)]
    private ClinicMembershipStatus $status;

    #[ORM\Column(name: 'valid_from_utc', type: 'datetime_immutable')]
    private \DateTimeImmutable $validFrom;

    #[ORM\Column(name: 'valid_until_utc', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $validUntil;

    #[ORM\Column(name: 'created_at_utc', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

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

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function setUserId(Uuid $userId): void
    {
        $this->userId = $userId;
    }

    public function getRole(): ClinicMemberRole
    {
        return $this->role;
    }

    public function setRole(ClinicMemberRole $role): void
    {
        $this->role = $role;
    }

    public function getEngagement(): ClinicMembershipEngagement
    {
        return $this->engagement;
    }

    public function setEngagement(ClinicMembershipEngagement $engagement): void
    {
        $this->engagement = $engagement;
    }

    public function getStatus(): ClinicMembershipStatus
    {
        return $this->status;
    }

    public function setStatus(ClinicMembershipStatus $status): void
    {
        $this->status = $status;
    }

    public function getValidFrom(): \DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function setValidFrom(\DateTimeImmutable $validFrom): void
    {
        $this->validFrom = $validFrom;
    }

    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeImmutable $validUntil): void
    {
        $this->validUntil = $validUntil;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
