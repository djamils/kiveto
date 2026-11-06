<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Account\SuspendClinic;

use App\Shared\Application\Bus\CommandInterface;

/**
 * C6 — « Suspendre la clinique »: effective at once, reversible. The reason
 * (SuspensionReason), the comment and who decided are kept with the account.
 */
final readonly class SuspendClinic implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $reason,
        public string $comment,
        public ?string $actorId,
        public string $actorName,
    ) {
    }
}
