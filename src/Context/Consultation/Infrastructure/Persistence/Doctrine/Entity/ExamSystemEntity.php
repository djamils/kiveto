<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_consultation_exam_system', columns: ['consultation_id', 'position'])]
class ExamSystemEntity implements ConsultationChildEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'binary', length: 16)]
    private string $id;

    #[ORM\Column(type: 'binary', length: 16)]
    private string $consultationId;

    // "system" is a reserved word in MySQL 8.0.3+: the quoted identifier makes
    // Doctrine backtick it in INSERT/UPDATE, which it does not do by default.
    #[ORM\Column(name: '`system`', type: 'string', length: 40)]
    private string $system;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /**
     * Drill-down fields, whose schema varies per body system.
     *
     * @var array<string, string>
     */
    #[ORM\Column(type: 'json')]
    private array $structuredData = [];

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

    public function getSystem(): string
    {
        return $this->system;
    }

    public function setSystem(string $system): void
    {
        $this->system = $system;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    /**
     * @return array<string, string>
     */
    public function getStructuredData(): array
    {
        return $this->structuredData;
    }

    /**
     * @param array<string, string> $structuredData
     */
    public function setStructuredData(array $structuredData): void
    {
        $this->structuredData = $structuredData;
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
