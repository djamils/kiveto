<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\StartSupportSession;

final readonly class SupportHandoff
{
    public function __construct(
        public string $sessionId,
        public string $url,
    ) {
    }
}
