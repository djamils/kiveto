<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\AcceptLinkSuggestion;

use App\Shared\Application\Bus\CommandInterface;

/**
 * `POST /me/link-suggestions/{id}/accept`: asks the clinic to link the
 * account to every matching file there. Returns the link status (`pending`);
 * `LinkSuggestionNotFoundException`.
 */
final readonly class AcceptLinkSuggestion implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $suggestionId,
    ) {
    }
}
