<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\PortalRequest;

use App\Context\ClientPortal\Application\Query\GetQuestionnaire\GetQuestionnaire;
use App\Context\ClientPortal\Application\Query\GetQuestionnaire\PortalQuestionnaire;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * The owner's pre-visit questionnaire as the agenda popover and the
 * consultation cockpit show it, in French words. Free text (treatment
 * details, notes) is handed as is: the page modules set it with
 * `textContent`, never as HTML.
 *
 * @phpstan-type QuestionnairePayload array{
 *     updatedAt: string, mayBite: bool, symptoms: list<string>, ongoingTreatment: bool,
 *     ongoingTreatmentDetails: ?string, temperament: string, notes: ?string
 * }
 */
final readonly class QuestionnaireView
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    /**
     * The submitted questionnaire of an appointment of the clinic, or null
     * when the owner sent none (or it was closed).
     *
     * @return QuestionnairePayload|null
     */
    public function of(string $clinicId, string $appointmentId): ?array
    {
        $questionnaire = $this->queryBus->ask(new GetQuestionnaire($clinicId, $appointmentId));

        $submitted = $questionnaire instanceof PortalQuestionnaire && PortalQuestionnaire::SUBMITTED === $questionnaire->status;

        if (!$submitted || null === $questionnaire->answers || null === $questionnaire->updatedAt) {
            return null;
        }

        $answers = $questionnaire->answers;

        return [
            'updatedAt'               => $questionnaire->updatedAt->format('d/m/Y à H:i'),
            'mayBite'                 => $questionnaire->mayBite,
            'symptoms'                => array_map(static fn (string $s): string => PortalRequestText::label(PortalRequestText::SYMPTOM, $s), $answers->symptoms),
            'ongoingTreatment'        => $answers->hasOngoingTreatment,
            'ongoingTreatmentDetails' => $answers->ongoingTreatmentDetails,
            'temperament'             => PortalRequestText::label(PortalRequestText::TEMPERAMENT, $answers->temperament),
            'notes'                   => $answers->notes,
        ];
    }
}
