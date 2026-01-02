<?php

declare(strict_types=1);

namespace App\Translation\Application\Command\BulkUpsertTranslations;

/**
 * @psalm-type BulkUpsertEntry = array{
 *     scope: string,
 *     locale: string,
 *     domain: string,
 *     key: string,
 *     value: string
 * }
 */
readonly class BulkUpsertTranslations
{
    /**
     * @param list<BulkUpsertEntry> $entries
     */
    public function __construct(
        public array $entries,
        public ?string $actorId = null,
    ) {
    }
}
