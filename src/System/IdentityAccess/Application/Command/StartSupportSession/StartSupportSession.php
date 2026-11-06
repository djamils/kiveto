<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\StartSupportSession;

use App\Shared\Application\Bus\CommandInterface;

/**
 * D1 — « Démarrer la session »: a support session as `userId` in `clinicId`.
 * Answers a SupportHandoff: the address of the clinic host that opens it,
 * usable once within two minutes. The caller checked that the person is a
 * member of the clinic and that writing is allowed there.
 */
final readonly class StartSupportSession implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $userId,
        public string $adminId,
        public string $adminName,
        public string $reason,
        public string $mode,
        public int $minutes,
    ) {
    }
}
