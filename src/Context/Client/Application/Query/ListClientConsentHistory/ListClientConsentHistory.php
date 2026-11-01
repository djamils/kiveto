<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientConsentHistory;

use App\Shared\Application\Bus\QueryInterface;

/**
 * One page of the consent history of some clients of a clinic, newest
 * first, keyset on `(at, id)`. Answers a CursorPage<ConsentHistoryItemView>;
 * a cursor of another shape throws InvalidCursorException.
 */
final readonly class ListClientConsentHistory implements QueryInterface
{
    /**
     * @param list<string> $clientIds
     */
    public function __construct(
        public string $clinicId,
        public array $clientIds,
        public ?string $cursor = null,
        public ?int $limit = null,
    ) {
    }
}
