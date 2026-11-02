<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Port;

/** Which clinics are open for business (Clinic context). */
interface ClinicStatusInterface
{
    /**
     * The given clinics whose status is `active`, as is their group's when
     * they have one: suspended, closed and unknown clinics, and those of a
     * suspended group, are left out.
     *
     * @param list<string> $clinicIds
     *
     * @return list<string>
     */
    public function activeClinicIds(array $clinicIds): array;
}
