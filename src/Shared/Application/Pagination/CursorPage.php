<?php

declare(strict_types=1);

namespace App\Shared\Application\Pagination;

/**
 * One page of a cursor-paginated list. `nextCursor` is the encoded cursor of
 * the following page, or null on the last page.
 *
 * @template T
 */
final readonly class CursorPage
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
    ) {
    }
}
