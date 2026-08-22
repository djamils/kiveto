<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\StockOverview;

/**
 * What the movement page asks for: a window on the trail.
 */
final readonly class MovementCriteria
{
    /**
     * @param list<string>      $types
     * @param list<string>      $reasons
     * @param list<string>|null $articleIds a narrowing decided elsewhere, typically
     *                                      the articles whose name matched a search
     */
    public function __construct(
        public array $types = [],
        public array $reasons = [],
        public ?array $articleIds = null,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public int $page = 1,
        public int $perPage = 25,
    ) {
    }

    public function offset(): int
    {
        return max(0, ($this->page - 1) * $this->perPage);
    }

    public function isImpossible(): bool
    {
        return null !== $this->articleIds && [] === $this->articleIds;
    }
}
