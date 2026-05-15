<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Entity;

use App\Shared\Domain\Time\ClockInterface;
use App\System\Taxation\Domain\ValueObject\MentionTemplateCondition;
use App\System\Taxation\Domain\ValueObject\MentionTemplateId;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;

final class MentionTemplate
{
    private MentionTemplateId $id;
    private TaxRegimeId $regimeId;
    private string $code;
    private MentionTemplateCondition $condition;
    private string $template;
    private string $locale;
    private bool $active;
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function create(
        MentionTemplateId $id,
        TaxRegimeId $regimeId,
        string $code,
        MentionTemplateCondition $condition,
        string $template,
        string $locale,
        ClockInterface $clock,
    ): self {
        $m            = new self();
        $m->id        = $id;
        $m->regimeId  = $regimeId;
        $m->code      = $code;
        $m->condition = $condition;
        $m->template  = $template;
        $m->locale    = $locale;
        $m->active    = true;
        $m->createdAt = $clock->now();

        return $m;
    }

    public static function reconstitute(
        MentionTemplateId $id,
        TaxRegimeId $regimeId,
        string $code,
        MentionTemplateCondition $condition,
        string $template,
        string $locale,
        bool $active,
        \DateTimeImmutable $createdAt,
    ): self {
        $m            = new self();
        $m->id        = $id;
        $m->regimeId  = $regimeId;
        $m->code      = $code;
        $m->condition = $condition;
        $m->template  = $template;
        $m->locale    = $locale;
        $m->active    = $active;
        $m->createdAt = $createdAt;

        return $m;
    }

    public function id(): MentionTemplateId
    {
        return $this->id;
    }

    public function regimeId(): TaxRegimeId
    {
        return $this->regimeId;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function condition(): MentionTemplateCondition
    {
        return $this->condition;
    }

    public function template(): string
    {
        return $this->template;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
