<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ActiveSubstanceId;

class ActiveSubstance extends AggregateRoot
{
    private ActiveSubstanceId $id;
    private string $label;
    private string $labelNormalized;
    private ?string $innCode;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function create(
        ActiveSubstanceId $id,
        string $label,
        ?string $innCode,
        \DateTimeImmutable $now,
    ): self {
        $substance                  = new self();
        $substance->id              = $id;
        $substance->label           = $label;
        $substance->labelNormalized = self::normalizeLabel($label);
        $substance->innCode         = $innCode;
        $substance->createdAt       = $now;
        $substance->updatedAt       = $now;

        return $substance;
    }

    public static function reconstitute(
        ActiveSubstanceId $id,
        string $label,
        string $labelNormalized,
        ?string $innCode,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $substance                  = new self();
        $substance->id              = $id;
        $substance->label           = $label;
        $substance->labelNormalized = $labelNormalized;
        $substance->innCode         = $innCode;
        $substance->createdAt       = $createdAt;
        $substance->updatedAt       = $updatedAt;

        return $substance;
    }

    public static function normalizeLabel(string $rawLabel): string
    {
        $normalized = mb_strtolower(mb_trim($rawLabel));

        return (string) preg_replace('/\s+/', ' ', $normalized);
    }

    public function id(): ActiveSubstanceId
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function labelNormalized(): string
    {
        return $this->labelNormalized;
    }

    public function innCode(): ?string
    {
        return $this->innCode;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
