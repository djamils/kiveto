<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\OwnerAppointmentsInterface;
use App\Context\ClientPortal\Application\Port\PortalPet;
use App\Context\ClientPortal\Application\Port\PreventiveAct;
use App\Context\ClientPortal\Application\Port\PreventiveCareInterface;
use App\Context\ClientPortal\Application\Query\GetOwnerHome\HomeCareItem;
use App\Context\ClientPortal\Application\Query\ListOwnerCareItems\OwnerCareItem;
use App\Shared\Domain\Time\ClockInterface;

/**
 * The care items of alive pets: the latest act of each protocol that has a
 * next due date (a protocol renewed without one disappears), its status
 * against the clinic-local today and the pet's appointments booked for a
 * motif of the same kind, the nearest due date first.
 */
final readonly class CareItems
{
    public function __construct(
        private PreventiveCareInterface $preventiveCare,
        private CareItemStatusPolicy $policy,
        private ClinicProfileInterface $clinics,
        private ClockInterface $clock,
        private OwnerAppointmentsInterface $appointments,
    ) {
    }

    /**
     * @param list<PortalPet> $pets
     *
     * @return list<HomeCareItem>
     */
    public function of(string $clinicId, array $pets): array
    {
        $names = [];

        foreach ($pets as $pet) {
            if ($pet->isAlive()) {
                $names[$pet->id] = $pet->name;
            }
        }

        if ([] === $names) {
            return [];
        }

        $timeZone = new \DateTimeZone($this->clinics->clinicOf($clinicId)->timeZone);
        $today    = CareItemStatusPolicy::dayIn($this->clock->now(), $timeZone);
        $items    = [];
        $booked   = [];

        foreach ($this->appointments->careBookings($clinicId, array_keys($names), $today) as $booking) {
            $booked[$booking->animalId][$booking->preventiveCareKind][] = CareItemStatusPolicy::dayIn($booking->startsAtUtc, $timeZone);
        }

        foreach ($this->preventiveCare->latestActs($clinicId, array_keys($names)) as $act) {
            $name = $names[$act->animalId] ?? null;

            if (null === $act->nextDueOn || null === $name) {
                continue;
            }

            $items[] = new HomeCareItem($act->animalId, $name, self::item($act, $act->nextDueOn, $this->policy->statusOf($act->nextDueOn, $today, $booked[$act->animalId][$act->kind] ?? [])));
        }

        usort($items, static fn (HomeCareItem $a, HomeCareItem $b): int => [$a->care->dueOn->format('Y-m-d'), $a->petName, $a->care->label]
            <=> [$b->care->dueOn->format('Y-m-d'), $b->petName, $b->care->label]);

        return $items;
    }

    private static function item(PreventiveAct $act, \DateTimeImmutable $dueOn, CareItemStatus $status): OwnerCareItem
    {
        return new OwnerCareItem($act->kind, $act->label, $act->administeredOn, $dueOn, $status);
    }
}
