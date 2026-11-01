<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\ArchiveBookingMotif;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Archives a motif of the clinic: it is no longer offered online; the
 * appointments already booked with it keep it.
 */
final readonly class ArchiveBookingMotif implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $motifId,
    ) {
    }
}
