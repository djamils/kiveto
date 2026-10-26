<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\RecordVeterinarianCount;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Clinic pushes the number of active veterinarians of a clinic for a day
 * (Y-m-d); the SMS quota of a month is based on the highest of its days.
 */
final readonly class RecordVeterinarianCount implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $day,
        public int $count,
    ) {
    }
}
