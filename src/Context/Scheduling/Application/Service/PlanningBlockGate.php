<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Service;

use App\Context\Scheduling\Application\Port\PlanningBlockFinderInterface;
use App\Context\Scheduling\Domain\Exception\BlockRefusesAppointmentsException;
use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Context\Scheduling\Domain\Service\BlockCapacity;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;

/**
 * The planning block rule of a VetApp booking or move (SOFT): no block
 * covering the slot = allowed; a block of a refusing type or a full block =
 * refused. Call it under the practitioner-day lock: the finder reads current.
 */
final readonly class PlanningBlockGate
{
    public function __construct(
        private PlanningBlockFinderInterface $finder,
    ) {
    }

    public function assertAdmits(
        ClinicId $clinicId,
        UserId $practitionerUserId,
        TimeSlot $timeSlot,
        \DateTimeZone $clinicTz,
        ?AppointmentId $movingAppointmentId = null,
    ): void {
        $startsLocal = $timeSlot->startsAtUtc()->setTimezone($clinicTz);
        $endsLocal   = $timeSlot->endsAtUtc()->setTimezone($clinicTz);

        $block = $this->finder->findActiveBlockFor(
            $clinicId,
            $practitionerUserId,
            $startsLocal->format('Y-m-d'),
            $startsLocal->format('H:i'),
            $endsLocal->format('H:i'),
            $movingAppointmentId,
        );

        if (null === $block) {
            return;
        }

        if (!$block->acceptsAppointments) {
            throw BlockRefusesAppointmentsException::ofType($block->type);
        }

        // The count covers the whole block occurrence, so does the capacity
        $windowMinutes = max($block->durationMinutes, $timeSlot->durationMinutes());
        if (BlockCapacity::isReached($block->capacityPerHour, $windowMinutes, $block->currentAppointmentCount)) {
            throw SlotTakenException::capacityReached();
        }
    }
}
