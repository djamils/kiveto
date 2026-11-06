<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RecordSupportPage;

use App\Shared\Application\Bus\CommandInterface;

/**
 * A page seen (or a change made, `write`) during a support session. Answers
 * whether the session is still open: false once it ended or ran out.
 */
final readonly class RecordSupportPage implements CommandInterface
{
    public function __construct(
        public string $sessionId,
        public bool $write,
    ) {
    }
}
