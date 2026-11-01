<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Request;

use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;

/**
 * The answers of the pre-visit questionnaire, version 1: symptoms (at
 * least one; `none` stands alone), an ongoing treatment (details at most
 * 500 characters, kept only when there is one), the temperament and free
 * notes (at most 1000 characters). Texts are trimmed; blank is none.
 */
final readonly class QuestionnaireAnswers
{
    public const int MAX_DETAILS = 500;
    public const int MAX_NOTES   = 1000;

    /** @var list<Symptom> */
    private array $symptoms;

    private bool $hasOngoingTreatment;
    private ?string $ongoingTreatmentDetails;
    private Temperament $temperament;
    private ?string $notes;

    /**
     * @param list<Symptom> $symptoms
     */
    private function __construct(array $symptoms, bool $hasOngoingTreatment, ?string $ongoingTreatmentDetails, Temperament $temperament, ?string $notes)
    {
        $this->symptoms                = $symptoms;
        $this->hasOngoingTreatment     = $hasOngoingTreatment;
        $this->ongoingTreatmentDetails = $ongoingTreatmentDetails;
        $this->temperament             = $temperament;
        $this->notes                   = $notes;
    }

    /**
     * @param list<string> $symptoms
     *
     * @throws InvalidPortalRequestException
     */
    public static function of(array $symptoms, bool $hasOngoingTreatment, ?string $ongoingTreatmentDetails, string $temperament, ?string $notes): self
    {
        $parsed = [];

        foreach ($symptoms as $symptom) {
            $parsed[$symptom] = Symptom::tryFrom($symptom) ?? throw new InvalidPortalRequestException('symptoms', \sprintf('Symptôme inconnu : « %s ».', $symptom));
        }

        if ([] === $parsed) {
            throw new InvalidPortalRequestException('symptoms', 'Indiquez au moins un symptôme, ou « aucun ».');
        }

        if (isset($parsed[Symptom::NONE->value]) && \count($parsed) > 1) {
            throw new InvalidPortalRequestException('symptoms', '« Aucun symptôme » ne se combine pas avec un autre symptôme.');
        }

        $details = self::text($hasOngoingTreatment ? $ongoingTreatmentDetails : null);
        $notes   = self::text($notes);

        if (null !== $details && mb_strlen($details) > self::MAX_DETAILS) {
            throw new InvalidPortalRequestException('ongoingTreatment.details', \sprintf('Le détail du traitement ne doit pas dépasser %d caractères.', self::MAX_DETAILS));
        }

        if (null !== $notes && mb_strlen($notes) > self::MAX_NOTES) {
            throw new InvalidPortalRequestException('notes', \sprintf('Les remarques ne doivent pas dépasser %d caractères.', self::MAX_NOTES));
        }

        return new self(
            array_values($parsed),
            $hasOngoingTreatment,
            $details,
            Temperament::tryFrom($temperament) ?? throw new InvalidPortalRequestException('temperament', \sprintf('Tempérament inconnu : « %s ».', $temperament)),
            $notes,
        );
    }

    /**
     * @param list<Symptom> $symptoms
     */
    public static function reconstitute(array $symptoms, bool $hasOngoingTreatment, ?string $ongoingTreatmentDetails, Temperament $temperament, ?string $notes): self
    {
        return new self($symptoms, $hasOngoingTreatment, $ongoingTreatmentDetails, $temperament, $notes);
    }

    /** Account deletion: the owner's texts erased, the choices kept. */
    public function withoutFreeText(): self
    {
        return new self($this->symptoms, $this->hasOngoingTreatment, null, $this->temperament, null);
    }

    /** @return list<Symptom> */
    public function symptoms(): array
    {
        return $this->symptoms;
    }

    public function hasOngoingTreatment(): bool
    {
        return $this->hasOngoingTreatment;
    }

    public function ongoingTreatmentDetails(): ?string
    {
        return $this->ongoingTreatmentDetails;
    }

    public function temperament(): Temperament
    {
        return $this->temperament;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function mayBite(): bool
    {
        return Temperament::MAY_BITE === $this->temperament;
    }

    private static function text(?string $value): ?string
    {
        return null === $value || '' === trim($value) ? null : trim($value);
    }
}
