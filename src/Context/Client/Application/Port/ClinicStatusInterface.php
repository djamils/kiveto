<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Port;

/** Which clinics are open for business (Clinic context). */
interface ClinicStatusInterface
{
    /**
     * The given clinics whose status is `active`: suspended, closed and
     * unknown clinics are left out.
     *
     * @param list<string> $clinicIds
     *
     * @return list<string>
     */
    public function activeClinicIds(array $clinicIds): array;
}
