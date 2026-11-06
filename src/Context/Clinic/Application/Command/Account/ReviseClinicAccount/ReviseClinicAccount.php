<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Account\ReviseClinicAccount;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Modifier la clinique »: plan, end of the pilot (Y-m-d), parallel
 * software, internal note.
 */
final readonly class ReviseClinicAccount implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $plan,
        public ?string $pilotEndsOn,
        public ?string $parallelSoftware,
        public string $internalNote,
    ) {
    }
}
