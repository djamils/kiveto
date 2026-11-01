<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\Repository;

use App\Context\Client\Domain\ValueObject\ConsentHistoryEntry;

/**
 * The consent history is append-only: one row per kind and channel granted
 * or withdrawn, never updated nor deleted.
 */
interface ConsentHistoryRepositoryInterface
{
    /**
     * @param list<ConsentHistoryEntry> $entries
     */
    public function append(array $entries): void;
}
