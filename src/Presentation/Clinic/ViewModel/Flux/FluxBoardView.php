<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Flux;

/**
 * The ambulatory flow board: who is booked, waiting, taken in charge or at
 * the desk right now, and who already left today.
 */
final readonly class FluxBoardView
{
    /**
     * @param list<FluxSlot>                                                           $planned
     * @param list<FluxPatient>                                                        $urgent
     * @param list<FluxPatient>                                                        $priority
     * @param list<FluxPatient>                                                        $standard
     * @param list<FluxPatient>                                                        $inCharge
     * @param list<FluxPatient>                                                        $checkout
     * @param list<FluxPassage>                                                        $passages             seen and gone today and no-shows, by booking time
     * @param array<string, array{appointmentId: string, time: string, motif: string}> $appointmentsByAnimal
     */
    public function __construct(
        public string $timeZone,
        public string $dateLabel,
        public string $clockLabel,
        public int $nowEpoch,
        public array $planned,
        public int $plannedCount,
        public array $urgent,
        public array $priority,
        public array $standard,
        public array $inCharge,
        public array $checkout,
        public array $passages,
        public int $finishedCount,
        public int $noShowCount,
        public int $onSiteCount,
        public int $averageWaitMinutes,
        public int $freePractitioners,
        public array $appointmentsByAnimal,
    ) {
    }

    /** The « Pris en charge » column splits into sections once an emergency is in it. */
    public function hasUrgentInCharge(): bool
    {
        foreach ($this->inCharge as $patient) {
            if ($patient->isEmergency) {
                return true;
            }
        }

        return false;
    }

    public function waitingCount(): int
    {
        return \count($this->urgent) + \count($this->priority) + \count($this->standard);
    }

    /** @return list<FluxPatient> */
    public function patients(): array
    {
        $planned = [];
        foreach ($this->planned as $slot) {
            array_push($planned, ...$slot->patients);
        }

        return [
            ...$planned,
            ...$this->urgent,
            ...$this->priority,
            ...$this->standard,
            ...$this->inCharge,
            ...$this->checkout,
        ];
    }
}
