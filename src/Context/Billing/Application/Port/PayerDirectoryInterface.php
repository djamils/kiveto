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

    /**
     * A client of the clinic, for what is sold to them at the desk. Null when
     * the client is not this clinic's.
     */
    public function forClient(string $clientId, string $clinicId): ?PayerSnapshotDto;

    /**
     * The active client of the clinic one of whose phone numbers is this one
     * (E.164), so the desk does not create the same person twice. Null when
     * nobody has it.
     */
    public function clientWithPhone(string $phone, string $clinicId): ?string;
}
