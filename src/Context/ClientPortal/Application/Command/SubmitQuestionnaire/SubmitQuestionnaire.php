<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\SubmitQuestionnaire;

use App\Shared\Application\Bus\CommandInterface;

/**
 * `PUT …/appointments/{id}/questionnaire`: writes (or rewrites) the
 * pre-visit questionnaire of an appointment of the account. Answers a
 * SubmitQuestionnaireOutcome: `LOCKED` is a result, not an exception, so
 * the lock it records survives (the API answers 422 `questionnaire_locked`).
 *
 * @throws \App\Context\ClientPortal\Domain\Exception\AppointmentNotFoundException  not the account's, cancelled or declined (404)
 * @throws \App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException version ≠ 1, symptoms, temperament, lengths (422)
 */
final readonly class SubmitQuestionnaire implements CommandInterface
{
    /**
     * @param list<string> $symptoms    none|cough|vomiting|diarrhea|appetite_loss|itching|lameness (`none` alone)
     * @param string       $temperament calm|stressed|may_bite
     */
    public function __construct(
        public string $userId,
        public string $clinicId,
        public string $appointmentId,
        public int $version,
        public array $symptoms,
        public bool $hasOngoingTreatment,
        public ?string $ongoingTreatmentDetails,
        public string $temperament,
        public ?string $notes,
    ) {
    }
}
