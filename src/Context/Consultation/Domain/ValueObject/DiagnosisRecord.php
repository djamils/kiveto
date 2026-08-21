<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class DiagnosisRecord
{
    private function __construct(
        private string $id,
        private ?string $code,
        private string $label,
        private DiagnosisCertainty $certainty,
        private ?string $note,
        private bool $isPrimary,
        private DiagnosisSource $source,
        private \DateTimeImmutable $createdAtUtc,
        private string $createdByUserId,
    ) {
    }

    public static function create(
        ?string $code,
        string $label,
        DiagnosisCertainty $certainty,
        ?string $note,
        bool $isPrimary,
        DiagnosisSource $source,
        \DateTimeImmutable $createdAtUtc,
        UserId $createdByUserId,
    ): self {
        return new self(
            Uuid::v7()->toString(),
            self::normalizeCode($code),
            self::normalizeLabel($label),
            $certainty,
            self::normalizeNote($note),
            $isPrimary,
            $source,
            $createdAtUtc,
            $createdByUserId->toString(),
        );
    }

    public static function reconstitute(
        string $id,
        ?string $code,
        string $label,
        DiagnosisCertainty $certainty,
        ?string $note,
        bool $isPrimary,
        DiagnosisSource $source,
        \DateTimeImmutable $createdAtUtc,
        string $createdByUserId,
    ): self {
        return new self($id, $code, $label, $certainty, $note, $isPrimary, $source, $createdAtUtc, $createdByUserId);
    }

    public function withDetails(
        ?string $code,
        string $label,
        DiagnosisCertainty $certainty,
        ?string $note,
    ): self {
        return new self(
            $this->id,
            self::normalizeCode($code),
            self::normalizeLabel($label),
            $certainty,
            self::normalizeNote($note),
            $this->isPrimary,
            $this->source,
            $this->createdAtUtc,
            $this->createdByUserId,
        );
    }

    public function withPrimary(bool $isPrimary): self
    {
        return new self(
            $this->id,
            $this->code,
            $this->label,
            $this->certainty,
            $this->note,
            $isPrimary,
            $this->source,
            $this->createdAtUtc,
            $this->createdByUserId,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCertainty(): DiagnosisCertainty
    {
        return $this->certainty;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function getSource(): DiagnosisSource
    {
        return $this->source;
    }

    public function getCreatedAtUtc(): \DateTimeImmutable
    {
        return $this->createdAtUtc;
    }

    public function getCreatedByUserId(): string
    {
        return $this->createdByUserId;
    }

    private static function normalizeLabel(string $label): string
    {
        $label = trim($label);

        if ('' === $label) {
            throw new \InvalidArgumentException('Diagnosis label cannot be empty');
        }

        if (mb_strlen($label) > 255) {
            throw new \InvalidArgumentException('Diagnosis label cannot exceed 255 characters');
        }

        return $label;
    }

    private static function normalizeCode(?string $code): ?string
    {
        if (null === $code) {
            return null;
        }

        $code = trim($code);

        if ('' === $code) {
            return null;
        }

        if (mb_strlen($code) > 40) {
            throw new \InvalidArgumentException('Diagnosis code cannot exceed 40 characters');
        }

        return $code;
    }

    private static function normalizeNote(?string $note): ?string
    {
        if (null === $note) {
            return null;
        }

        $note = trim($note);

        if ('' === $note) {
            return null;
        }

        if (mb_strlen($note) > 5000) {
            throw new \InvalidArgumentException('Diagnosis note cannot exceed 5000 characters');
        }

        return $note;
    }
}
