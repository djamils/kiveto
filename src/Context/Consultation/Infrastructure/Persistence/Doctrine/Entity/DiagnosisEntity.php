<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_consultation_diagnosis', columns: ['consultation_id', 'position'])]
class DiagnosisEntity implements ConsultationChildEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'binary', length: 16)]
    private string $id;

    #[ORM\Column(type: 'binary', length: 16)]
    private string $consultationId;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $label;

    #[ORM\Column(type: 'string', length: 20)]
    private string $certainty;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isPrimary = false;

    #[ORM\Column(type: 'string', length: 20)]
    private string $source;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAtUtc;

    #[ORM\Column(type: 'binary', length: 16)]
    private string $createdByUserId;

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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getCertainty(): string
    {
        return $this->certainty;
    }

    public function setCertainty(string $certainty): void
    {
        $this->certainty = $certainty;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): void
    {
        $this->isPrimary = $isPrimary;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): void
    {
        $this->source = $source;
    }

    public function getCreatedAtUtc(): \DateTimeImmutable
    {
        return $this->createdAtUtc;
    }

    public function setCreatedAtUtc(\DateTimeImmutable $createdAtUtc): void
    {
        $this->createdAtUtc = $createdAtUtc;
    }

    public function getCreatedByUserId(): string
    {
        return $this->createdByUserId;
    }

    public function setCreatedByUserId(string $createdByUserId): void
    {
        $this->createdByUserId = $createdByUserId;
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
