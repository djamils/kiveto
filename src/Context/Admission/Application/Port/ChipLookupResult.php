<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Port;

/**
 * Sealed result type for a microchip lookup operation.
 *
 * Implementations:
 * - AnimalNotInClinic          – no animal matched this chip in the clinic
 * - AnimalInClinicWithActivePatient    – animal found and has an active patient record
 * - AnimalInClinicWithoutActivePatient – animal found but no active patient record
 */
interface ChipLookupResult
{
}
