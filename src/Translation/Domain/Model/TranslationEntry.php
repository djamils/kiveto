<?php

declare(strict_types=1);

namespace App\Translation\Domain\Model;

use App\Translation\Domain\Model\ValueObject\ActorId;
use App\Translation\Domain\Model\ValueObject\TranslationKey;
use App\Translation\Domain\Model\ValueObject\TranslationText;

final class TranslationEntry
{
    public function __construct(
        private TranslationKey $key,
        private TranslationText $text,
        private \DateTimeImmutable $updatedAt,
        private ?ActorId $updatedBy = null,
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

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updatedBy(): ?ActorId
    {
        return $this->updatedBy;
    }

    public function replaceText(TranslationText $text, \DateTimeImmutable $updatedAt, ?ActorId $updatedBy): self
    {
        return new self($this->key, $text, $updatedAt, $updatedBy);
    }
}
