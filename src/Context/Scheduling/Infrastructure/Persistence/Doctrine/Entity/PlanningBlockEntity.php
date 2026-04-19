<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'scheduling__planning_blocks')]
#[ORM\Index(name: 'idx_pb_clinic_date', columns: ['clinic_id', 'date'])]
#[ORM\Index(name: 'idx_pb_clinic_pract', columns: ['clinic_id', 'practitioner_user_id', 'date'])]
class PlanningBlockEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(name: 'practitioner_user_id', type: UuidType::NAME)]
    private Uuid $practitionerUserId;

    #[ORM\Column(type: 'string', length: 50, enumType: PlanningBlockType::class)]
    private PlanningBlockType $type;

    #[ORM\Column(type: 'string', length: 10)]
    private string $date;

    #[ORM\Column(name: 'start_time', type: 'string', length: 5)]
    private string $startTime;

    #[ORM\Column(name: 'end_time', type: 'string', length: 5)]
    private string $endTime;

    #[ORM\Column(name: 'capacity_per_hour', type: 'integer')]
    private int $capacityPerHour;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'recurrence_rule', type: 'json')]
    private array $recurrenceRule;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $note;

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

    public function getPractitionerUserId(): Uuid
    {
        return $this->practitionerUserId;
    }

    public function setPractitionerUserId(Uuid $practitionerUserId): void
    {
        $this->practitionerUserId = $practitionerUserId;
    }

    public function getType(): PlanningBlockType
    {
        return $this->type;
    }

    public function setType(PlanningBlockType $type): void
    {
        $this->type = $type;
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function setDate(string $date): void
    {
        $this->date = $date;
    }

    public function getStartTime(): string
    {
        return $this->startTime;
    }

    public function setStartTime(string $startTime): void
    {
        $this->startTime = $startTime;
    }

    public function getEndTime(): string
    {
        return $this->endTime;
    }

    public function setEndTime(string $endTime): void
    {
        $this->endTime = $endTime;
    }

    public function getCapacityPerHour(): int
    {
        return $this->capacityPerHour;
    }

    public function setCapacityPerHour(int $capacityPerHour): void
    {
        $this->capacityPerHour = $capacityPerHour;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRecurrenceRule(): array
    {
        return $this->recurrenceRule;
    }

    /**
     * @param array<string, mixed> $recurrenceRule
     */
    public function setRecurrenceRule(array $recurrenceRule): void
    {
        $this->recurrenceRule = $recurrenceRule;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
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
