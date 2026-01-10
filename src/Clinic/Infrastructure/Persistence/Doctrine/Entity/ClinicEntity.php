<?php

declare(strict_types=1);

namespace App\Clinic\Infrastructure\Persistence\Doctrine\Entity;

use App\Clinic\Domain\ValueObject\ClinicStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_clinic_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_clinic_group_id', columns: ['clinic_group_id'])]
#[ORM\Index(name: 'idx_clinic_status', columns: ['status'])]
class ClinicEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_group_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $clinicGroupId = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 20, enumType: ClinicStatus::class)]
    private ClinicStatus $status;

    #[ORM\Column(name: 'time_zone', type: 'string', length: 64)]
    private string $timeZone;

    #[ORM\Column(type: 'string', length: 16)]
    private string $locale;

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

    public function getClinicGroupId(): ?Uuid
    {
        return $this->clinicGroupId;
    }

    public function setClinicGroupId(?Uuid $clinicGroupId): void
    {
        $this->clinicGroupId = $clinicGroupId;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getStatus(): ClinicStatus
    {
        return $this->status;
    }

    public function setStatus(ClinicStatus $status): void
    {
        $this->status = $status;
    }

    public function getTimeZone(): string
    {
        return $this->timeZone;
    }

    public function setTimeZone(string $timeZone): void
    {
        $this->timeZone = $timeZone;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
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
