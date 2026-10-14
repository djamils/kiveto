<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Flux;

/**
 * One patient card of the ambulatory flow board — a booked appointment not
 * arrived yet (« Planifié ») or an open admission (waiting room, taken in
 * charge, at the desk). Public properties only: the page module reads the
 * same object, JSON-encoded, to fill the patient peek and the action menu.
 */
final readonly class FluxPatient
{
    /**
     * @param list<string> $alerts medical alerts of the animal (« vigilances »)
     */
    public function __construct(
        public string $key,
        public string $column,
        public ?string $admissionId,
        public ?string $appointmentId,
        public ?string $animalId,
        public ?string $consultationId,
        public string $emoji,
        public string $name,
        public string $breed,
        public string $breedAndAge,
        public string $owner,
        public string $ownerShort,
        public ?string $phone,
        public string $motif,
        public ?string $practitioner,
        public int $sinceEpoch,
        public string $sinceLabel,
        public string $channel,
        public string $triageLevel,
        public bool $isEmergency,
        public bool $isPriority,
        public bool $isUnidentified,
        public bool $isLong,
        public string $waitLabel,
        public string $location,
        public array $alerts,
        public ?string $amountLabel,
        public ?string $slotLabel,
    ) {
    }
}
