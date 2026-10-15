<?php

declare(strict_types=1);

namespace App\Context\Admission\Domain\ValueObject;

/**
 * What the desk notes when a booked patient arrives, for the practitioner
 * who takes it in charge: the weighing, four things to flag (fasting, a new
 * symptom since the booking, documents brought, an ongoing treatment) and a
 * short free note.
 */
final readonly class ArrivalCheckIn
{
    public const int NOTE_MAX_LENGTH = 140;

    /** The desk scale stops at 120 kg: anything above is a typing error. */
    public const int MAX_WEIGHT_GRAMS = 120_000;

    private function __construct(
        public bool $fasting,
        public bool $newSymptom,
        public bool $documentsBrought,
        public bool $ongoingTreatment,
        public ?int $weightGrams,
        public ?string $note,
    ) {
    }

    public static function record(
        bool $fasting,
        bool $newSymptom,
        bool $documentsBrought,
        bool $ongoingTreatment,
        ?int $weightGrams,
        ?string $note,
    ): self {
        if (null !== $weightGrams && ($weightGrams <= 0 || $weightGrams > self::MAX_WEIGHT_GRAMS)) {
            throw new \InvalidArgumentException('Le poids saisi à l\'accueil doit être compris entre 0 et 120 kg.');
        }

        $note = trim((string) preg_replace('/\s+/u', ' ', (string) $note));

        if (mb_strlen($note) > self::NOTE_MAX_LENGTH) {
            throw new \InvalidArgumentException('La note d\'accueil ne peut dépasser 140 caractères.');
        }

        return new self($fasting, $newSymptom, $documentsBrought, $ongoingTreatment, $weightGrams, '' === $note ? null : $note);
    }

    /**
     * @param array{fasting?: bool, newSymptom?: bool, documentsBrought?: bool, ongoingTreatment?: bool, weightGrams?: int|null, note?: string|null} $data
     */
    public static function fromArray(array $data): self
    {
        return self::record(
            fasting: $data['fasting'] ?? false,
            newSymptom: $data['newSymptom'] ?? false,
            documentsBrought: $data['documentsBrought'] ?? false,
            ongoingTreatment: $data['ongoingTreatment'] ?? false,
            weightGrams: $data['weightGrams'] ?? null,
            note: $data['note'] ?? null,
        );
    }

    /**
     * @return array{fasting: bool, newSymptom: bool, documentsBrought: bool, ongoingTreatment: bool, weightGrams: int|null, note: string|null}
     */
    public function toArray(): array
    {
        return [
            'fasting'          => $this->fasting,
            'newSymptom'       => $this->newSymptom,
            'documentsBrought' => $this->documentsBrought,
            'ongoingTreatment' => $this->ongoingTreatment,
            'weightGrams'      => $this->weightGrams,
            'note'             => $this->note,
        ];
    }
}
