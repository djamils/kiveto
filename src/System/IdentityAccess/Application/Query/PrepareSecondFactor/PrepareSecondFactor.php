<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\PrepareSecondFactor;

use App\Shared\Application\Bus\QueryInterface;

/**
 * A fresh key to scan, labelled with the account's address. Nothing is
 * stored until a code of it is typed (EnableSecondFactor).
 */
final readonly class PrepareSecondFactor implements QueryInterface
{
    public function __construct(
        public string $email,
    ) {
    }
}
