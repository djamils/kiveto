<?php

declare(strict_types=1);

namespace App\Translation\Domain;

use App\Translation\Domain\ValueObject\ActorId;
use App\Translation\Domain\ValueObject\TranslationKey;
use App\Translation\Domain\ValueObject\TranslationText;

final class TranslationEntry
{
    public function __construct(
        private TranslationKey $key,
        private TranslationText $text,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
        private ?ActorId $createdBy = null,
        private ?ActorId $updatedBy = null,
        private ?string $description = null,
    ) {
    }

    public function key(): TranslationKey
    {
        return $this->key;
    }

    public function text(): TranslationText
    {
        return $this->text;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function createdBy(): ?ActorId
    {
        return $this->createdBy;
    }

    public function updatedBy(): ?ActorId
    {
        return $this->updatedBy;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function replaceText(
        TranslationText $text,
        \DateTimeImmutable $updatedAt,
        ?ActorId $updatedBy,
        ?string $description,
    ): self {
        return new self(
            $this->key,
            $text,
            $this->createdAt,
            $updatedAt,
            $this->createdBy,
            $updatedBy,
            $description,
        );
    }
}
