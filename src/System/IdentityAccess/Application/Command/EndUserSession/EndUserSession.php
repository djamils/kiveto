<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\EndUserSession;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The holder signed out: the session leaves « Sessions actives ».
 */
final readonly class EndUserSession implements CommandInterface
{
    public function __construct(
        public string $sessionId,
    ) {
    }
}
