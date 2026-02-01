<?php

declare(strict_types=1);

namespace App\ClinicalCare\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class PerformedActRecord
{
    private function __construct(
        private string $id,
        private string $label,
        private float $quantity,
        private \DateTimeImmutable $performedAtUtc,
        private \DateTimeImmutable $createdAtUtc,
        private string $createdByUserId,
    ) {
    }

    public static function create(
        string $label,
        float $quantity,
        \DateTimeImmutable $performedAtUtc,
        \DateTimeImmutable $createdAtUtc,
        UserId $createdByUserId,
    ): self {
        if (trim($label) === '') {
            throw new \InvalidArgumentException('Act label cannot be empty');
        }

        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Act quantity must be positive');
        }

        return new self(
            Uuid::v7()->toString(),
            $label,
            $quantity,
            $performedAtUtc,
            $createdAtUtc,
            $createdByUserId->toString(),
        );
    }

    public static function reconstitute(
        string $id,
        string $label,
        float $quantity,
        \DateTimeImmutable $performedAtUtc,
        \DateTimeImmutable $createdAtUtc,
        string $createdByUserId,
    ): self {
        return new self($id, $label, $quantity, $performedAtUtc, $createdAtUtc, $createdByUserId);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getPerformedAtUtc(): \DateTimeImmutable
    {
        return $this->performedAtUtc;
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
