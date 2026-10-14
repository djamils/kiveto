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
     * @param list<string>|null $articleIds     a narrowing decided elsewhere, typically
     *                                          the controlled substances of the register
     * @param string|null       $term           matched against the lot number, the
     *                                          reference and the note
     * @param list<string>      $termArticleIds the articles whose name matched the term,
     *                                          as the catalogue answered it
     */
    public function __construct(
        public array $types = [],
        public array $reasons = [],
        public ?array $articleIds = null,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public int $page = 1,
        public int $perPage = 25,
        public ?string $term = null,
        public array $termArticleIds = [],
        public ?string $lotId = null,
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
