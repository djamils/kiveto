<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * The opaque id of a link suggestion: derived from the account and the
 * clinic with a server secret, so it names neither the clinic nor a client
 * file and cannot be forged for another account.
 */
interface SuggestionIdSignerInterface
{
    public function idOf(string $userId, string $clinicId): string;
}
