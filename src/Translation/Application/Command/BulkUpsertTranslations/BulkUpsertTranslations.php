<?php

declare(strict_types=1);

namespace App\Translation\Application\Command\BulkUpsertTranslations;

use App\Shared\Application\Bus\CommandInterface;

/**
 * @psalm-type BulkUpsertEntry = array{
 *     scope: string,
 *     locale: string,
 *     domain: string,
 *     key: string,
 *     value: string,
 *     description?: string|null
 * }
 */
final readonly class BulkUpsertTranslations implements CommandInterface
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
