<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_consultation_typed_vital', columns: ['consultation_id', 'position'])]
class TypedVitalEntity implements ConsultationChildEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'binary', length: 16)]
    private string $id;

    #[ORM\Column(type: 'binary', length: 16)]
    private string $consultationId;

    #[ORM\Column(type: 'string', length: 40)]
    private string $type;

    #[ORM\Column(type: 'string', length: 60)]
    private string $value;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $recordedAtUtc;

    #[ORM\Column(type: 'binary', length: 16)]
    private string $recordedByUserId;

    #[ORM\Column(type: 'smallint')]
    private int $position = 0;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getConsultationId(): string
    {
        return $this->consultationId;
    }

    public function setConsultationId(string $consultationId): void
    {
        $this->consultationId = $consultationId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getRecordedAtUtc(): \DateTimeImmutable
    {
        return $this->recordedAtUtc;
    }

    public function setRecordedAtUtc(\DateTimeImmutable $recordedAtUtc): void
    {
        $this->recordedAtUtc = $recordedAtUtc;
    }

    public function getRecordedByUserId(): string
    {
        return $this->recordedByUserId;
    }

    public function setRecordedByUserId(string $recordedByUserId): void
    {
        $this->recordedByUserId = $recordedByUserId;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}
