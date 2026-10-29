<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetOtpChallengePhone;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The phone of a challenge (E.164 string), or null when unknown: lets the
 * API answer 404 and key its per-phone quotas before resending a code.
 */
final readonly class GetOtpChallengePhone implements QueryInterface
{
    public function __construct(
        public string $challengeId,
    ) {
    }
}
