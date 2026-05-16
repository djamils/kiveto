<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Entity;

use App\System\PharmaceuticalRegistry\Domain\ValueObject\SummaryLink;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\SummarySection;

final class Summary
{
    /** @var SummarySection[] */
    private array $sections;
    private ?SummaryLink $sourceUrl;
    private ?\DateTimeImmutable $lastUpdatedAt;

    /**
     * @param SummarySection[] $sections
     */
    private function __construct(
        array $sections,
        ?SummaryLink $sourceUrl,
        ?\DateTimeImmutable $lastUpdatedAt,
    ) {
        $this->sections      = $sections;
        $this->sourceUrl     = $sourceUrl;
        $this->lastUpdatedAt = $lastUpdatedAt;
    }

    /**
     * @param SummarySection[] $sections
     */
    public static function of(
        array $sections,
        ?SummaryLink $sourceUrl,
        ?\DateTimeImmutable $lastUpdatedAt,
    ): self {
        return new self($sections, $sourceUrl, $lastUpdatedAt);
    }

    /** @return SummarySection[] */
    public function sections(): array
    {
        return $this->sections;
    }

    public function sourceUrl(): ?SummaryLink
    {
        return $this->sourceUrl;
    }

    public function lastUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->lastUpdatedAt;
    }
}
