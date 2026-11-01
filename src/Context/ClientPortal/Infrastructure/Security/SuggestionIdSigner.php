<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Security;

use App\Context\ClientPortal\Application\Port\SuggestionIdSignerInterface;

/**
 * HMAC-SHA256 of `(userId, clinicId)` keyed by `%kernel.secret%`, in hex:
 * stable for an account and a clinic, meaningless to anyone else.
 */
final readonly class SuggestionIdSigner implements SuggestionIdSignerInterface
{
    public function __construct(
        #[\SensitiveParameter]
        private string $secret,
    ) {
    }

    public function idOf(string $userId, string $clinicId): string
    {
        return hash_hmac('sha256', 'link-suggestion|' . $userId . '|' . $clinicId, $this->secret);
    }
}
