<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListLinkSuggestions;

use App\Shared\Application\Bus\QueryInterface;

/**
 * `GET /me/link-suggestions`: the clinics holding a client file with the
 * account's mobile, one per clinic. Answers list<LinkSuggestion>; only the
 * clinic's name and city leave the context, never a client file.
 */
final readonly class ListLinkSuggestions implements QueryInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
