<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientsByMobilePhone;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Across every clinic: the client files whose `mobile`-labelled phone is
 * exactly this number (E.164, `+33612345678`). Archived clients and clinics
 * that are not active (suspended, closed) are left out. Answers
 * list<MobileClientMatch>, by clinic then oldest file first. Used by the
 * Portal's link suggestions: callers must reveal no more than they need.
 */
final readonly class ListClientsByMobilePhone implements QueryInterface
{
    public function __construct(
        public string $phone,
    ) {
    }
}
