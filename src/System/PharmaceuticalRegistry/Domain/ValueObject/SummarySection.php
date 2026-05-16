<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

final class SummarySection
{
    private int $titleCode;
    private string $titleLabel;
    private string $content;

    public function __construct(int $titleCode, string $titleLabel, string $content)
    {
        $this->titleCode  = $titleCode;
        $this->titleLabel = $titleLabel;
        $this->content    = $content;
    }

    public function titleCode(): int
    {
        return $this->titleCode;
    }

    public function titleLabel(): string
    {
        return $this->titleLabel;
    }

    public function content(): string
    {
        return $this->content;
    }
}
