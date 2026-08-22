<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

interface PayerDirectoryInterface
{
    /**
     * Who pays for a visit: the primary owner of the admitted animal.
     *
     * Returns null when the visit has no identified owner yet — an emergency
     * intake by a passer-by, a stray brought in by the police.
     */
    public function forAdmission(string $admissionId, string $clinicId): ?PayerSnapshotDto;
}
