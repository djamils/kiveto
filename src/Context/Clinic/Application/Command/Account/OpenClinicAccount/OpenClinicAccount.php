<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Account\OpenClinicAccount;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The console created a clinic: its plan (`pilot`, `paid`, `internal`), the
 * end of its pilot (Y-m-d) and the software it keeps alongside.
 */
final readonly class OpenClinicAccount implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $plan,
        public ?string $pilotEndsOn = null,
        public ?string $parallelSoftware = null,
    ) {
    }
}
