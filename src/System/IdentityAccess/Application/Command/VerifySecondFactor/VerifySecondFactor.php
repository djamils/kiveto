<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\VerifySecondFactor;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The second step of a back-office sign-in: six digits of the app, or a
 * backup code (spent). Never throws on a wrong code.
 */
final readonly class VerifySecondFactor implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $code,
    ) {
    }
}
