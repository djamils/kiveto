<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\OwnerAppointment;
use App\Context\ClientPortal\Application\Port\PortalPet;
use App\Context\ClientPortal\Application\Port\PractitionerNamesInterface;
use App\Context\ClientPortal\Application\Query\ListOwnerAppointments\PortalAppointment;
use App\Context\ClientPortal\Application\Query\ListOwnerCareItems\OwnerCareItem;

/**
 * Turns Scheduling's appointments into the owner's view: pet names from
 * the visible pets, printed practitioner names looked up once, instants in the
 * clinic's time zone, the related care item of the motif's kind.
 */
final readonly class PortalAppointments
{
    public function __construct(
        private CareItems $careItems,
        private PractitionerNamesInterface $practitioners,
        private ClinicProfileInterface $clinics,
    ) {
    }

    /**
     * Whether the appointment is one of the account's: of one of its client
     * files, or of one of the pets it sees.
     *
     * @param list<string>    $clientIds
     * @param list<PortalPet> $pets
     */
    public static function belongsTo(OwnerAppointment $appointment, array $clientIds, array $pets): bool
    {
        return \in_array($appointment->ownerId, $clientIds, true)
            || \in_array($appointment->animalId, array_map(static fn (PortalPet $pet): string => $pet->id, $pets), true);
    }

    /**
     * @param list<OwnerAppointment> $appointments
     * @param list<PortalPet>        $pets         the pets the account sees
     *
     * @return list<PortalAppointment>
     */
    public function views(string $clinicId, array $appointments, array $pets): array
    {
        if ([] === $appointments) {
            return [];
        }

        $timeZone = new \DateTimeZone($this->clinics->clinicOf($clinicId)->timeZone);
        $names    = array_map(static fn (OwnerAppointment $a): string => $a->practitionerUserId, $appointments);
        $names    = $this->practitioners->namesOf($clinicId, array_values(array_unique($names)));
        $petNames = [];

        foreach ($pets as $pet) {
            $petNames[$pet->id] = $pet->name;
        }

        $care  = $this->relatedCare($clinicId, $appointments, $pets);
        $views = [];

        foreach ($appointments as $appointment) {
            $petName = null === $appointment->animalId ? null : ($petNames[$appointment->animalId] ?? null);
            $startAt = $appointment->startsAtUtc->setTimezone($timeZone);

            $views[] = new PortalAppointment(
                $appointment->id,
                $startAt,
                $startAt->modify('+' . $appointment->durationMinutes . ' minutes'),
                null === $petName ? null : $appointment->animalId,
                $petName,
                $appointment->motifId,
                $appointment->motifLabel ?? PortalAppointment::NO_MOTIF_LABEL,
                $appointment->practitionerUserId,
                $names[$appointment->practitionerUserId] ?? null,
                $appointment->status,
                $appointment->occupiesSlot,
                $appointment->isPending,
                $appointment->source,
                $appointment->cancellableUntil?->setTimezone($timeZone),
                PortalAppointment::QUESTIONNAIRE_NOT_STARTED,
                $care[$appointment->animalId ?? ''][$appointment->preventiveCareKind ?? ''] ?? null,
            );
        }

        return $views;
    }

    /**
     * The nearest care item of each (pet, kind) a motif of the page stands
     * for; the care items are read only when one does.
     *
     * @param list<OwnerAppointment> $appointments
     * @param list<PortalPet>        $pets
     *
     * @return array<string, array<string, OwnerCareItem>> by pet id, then kind
     */
    private function relatedCare(string $clinicId, array $appointments, array $pets): array
    {
        $wanted = array_filter($appointments, static fn (OwnerAppointment $a): bool => null !== $a->animalId && null !== $a->preventiveCareKind);

        if ([] === $wanted) {
            return [];
        }

        $care = [];

        foreach ($this->careItems->of($clinicId, $pets) as $item) {
            $care[$item->petId][$item->care->kind] ??= $item->care;
        }

        return $care;
    }
}
