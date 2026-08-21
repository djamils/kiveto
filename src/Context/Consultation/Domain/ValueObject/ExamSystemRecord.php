<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * Result of the clinical exam for one body system.
 *
 * `structuredData` holds the drill-down fields of the system-specific form. Its
 * schema varies per system (see {@see BodySystem::drilldown()}) so it is stored
 * as a JSON column rather than typed columns:
 *   - cardio: {fc: string, rhythm: string, murmur: string}
 *   - loco:   {limb: string, grade: string, type: string, region: string}
 *   - derma:  {region: string, lesion: string}
 * Values are always scalars normalised to strings; empty entries are dropped.
 */
final readonly class ExamSystemRecord
{
    /**
     * @param array<string, string> $structuredData
     */
    private function __construct(
        private string $id,
        private BodySystem $system,
        private ExamStatus $status,
        private ?string $notes,
        private array $structuredData,
        private \DateTimeImmutable $recordedAtUtc,
        private string $recordedByUserId,
    ) {
    }

    /**
     * @param array<string, string> $structuredData
     */
    public static function create(
        BodySystem $system,
        ExamStatus $status,
        ?string $notes,
        array $structuredData,
        \DateTimeImmutable $recordedAtUtc,
        UserId $recordedByUserId,
    ): self {
        $notes = null !== $notes ? trim($notes) : null;

        if ('' === $notes) {
            $notes = null;
        }

        if (null !== $notes && mb_strlen($notes) > 5000) {
            throw new \InvalidArgumentException('Exam notes cannot exceed 5000 characters');
        }

        $cleaned = [];
        foreach ($structuredData as $key => $value) {
            $value = trim($value);

            if ('' === $value) {
                continue;
            }

            if (mb_strlen($value) > 255) {
                throw new \InvalidArgumentException(\sprintf(
                    'Exam structured field "%s" cannot exceed 255 characters',
                    $key,
                ));
            }

            $cleaned[$key] = $value;
        }

        return new self(
            Uuid::v7()->toString(),
            $system,
            $status,
            $notes,
            $cleaned,
            $recordedAtUtc,
            $recordedByUserId->toString(),
        );
    }

    /**
     * @param array<string, string> $structuredData
     */
    public static function reconstitute(
        string $id,
        BodySystem $system,
        ExamStatus $status,
        ?string $notes,
        array $structuredData,
        \DateTimeImmutable $recordedAtUtc,
        string $recordedByUserId,
    ): self {
        return new self($id, $system, $status, $notes, $structuredData, $recordedAtUtc, $recordedByUserId);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSystem(): BodySystem
    {
        return $this->system;
    }

    public function getStatus(): ExamStatus
    {
        return $this->status;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    /**
     * @return array<string, string>
     */
    public function getStructuredData(): array
    {
        return $this->structuredData;
    }

    public function getRecordedAtUtc(): \DateTimeImmutable
    {
        return $this->recordedAtUtc;
    }

    public function getRecordedByUserId(): string
    {
        return $this->recordedByUserId;
    }

    public function withStatus(ExamStatus $status, \DateTimeImmutable $recordedAtUtc): self
    {
        return new self(
            $this->id,
            $this->system,
            $status,
            $this->notes,
            $this->structuredData,
            $recordedAtUtc,
            $this->recordedByUserId,
        );
    }
}
