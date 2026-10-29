<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\IsPortalMobileNumber;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Whether this E.164 number (`+33612345678`) may be used with the client
 * Portal: a mobile of the ranges `MobileNumberPolicy` knows, the single rule
 * shared by Portal invitations and the Portal API. Answers a bool.
 */
final readonly class IsPortalMobileNumber implements QueryInterface
{
    public function __construct(
        public string $phone,
    ) {
    }
}
