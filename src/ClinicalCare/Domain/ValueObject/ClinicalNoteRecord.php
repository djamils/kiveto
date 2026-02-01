<?php

declare(strict_types=1);

namespace App\ClinicalCare\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class ClinicalNoteRecord
{
    private function __construct(
        private string $id,
        private NoteType $noteType,
        private string $content,
        private \DateTimeImmutable $createdAtUtc,
        private string $createdByUserId,
    ) {
    }

    public static function create(
        NoteType $noteType,
        string $content,
        \DateTimeImmutable $createdAtUtc,
        UserId $createdByUserId,
    ): self {
        if (trim($content) === '') {
            throw new \InvalidArgumentException('Clinical note content cannot be empty');
        }

        return new self(
            Uuid::v7()->toString(),
            $noteType,
            $content,
            $createdAtUtc,
            $createdByUserId->toString(),
        );
    }

    public static function reconstitute(
        string $id,
        NoteType $noteType,
        string $content,
        \DateTimeImmutable $createdAtUtc,
        string $createdByUserId,
    ): self {
        return new self($id, $noteType, $content, $createdAtUtc, $createdByUserId);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getNoteType(): NoteType
    {
        return $this->noteType;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getCreatedAtUtc(): \DateTimeImmutable
    {
        return $this->createdAtUtc;
    }

    public function getCreatedByUserId(): string
    {
        return $this->createdByUserId;
    }
}
