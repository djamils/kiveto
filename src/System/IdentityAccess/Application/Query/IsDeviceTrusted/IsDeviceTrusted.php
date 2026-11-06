<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\IsDeviceTrusted;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Does the cookie `secret` trust this browser for the account?
 */
final readonly class IsDeviceTrusted implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $secret,
    ) {
    }
}
