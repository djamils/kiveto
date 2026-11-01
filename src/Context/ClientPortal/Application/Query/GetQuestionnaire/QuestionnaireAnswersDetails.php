<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetQuestionnaire;

/** The owner's answers, as written (free texts are rendered as text, never as HTML). */
final readonly class QuestionnaireAnswersDetails
{
    /**
     * @param list<string> $symptoms    none|cough|vomiting|diarrhea|appetite_loss|itching|lameness
     * @param string       $temperament calm|stressed|may_bite
     */
    public function __construct(
        public array $symptoms,
        public bool $hasOngoingTreatment,
        public ?string $ongoingTreatmentDetails,
        public string $temperament,
        public ?string $notes,
    ) {
    }
}
