<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetQuestionnaire;

use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Service\QuestionnaireAccess;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaire;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaireRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Context\ClientPortal\Domain\Request\Symptom;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetQuestionnaireHandler
{
    public function __construct(
        private QuestionnaireAccess $access,
        private PreVisitQuestionnaireRepositoryInterface $questionnaires,
        private ClinicProfileInterface $clinics,
    ) {
    }

    public function __invoke(GetQuestionnaire $query): PortalQuestionnaire
    {
        $appointment = null === $query->userId
            ? $this->access->appointment($query->clinicId, $query->appointmentId)
            : $this->access->ownedAppointment($query->userId, $query->clinicId, $query->appointmentId)->appointment;

        $questionnaire = $this->questionnaires->findByAppointment($query->clinicId, $query->appointmentId);
        $editable      = null !== $appointment && $this->access->isOpen($query->clinicId, $appointment, $questionnaire);

        if (null === $questionnaire) {
            return new PortalQuestionnaire($query->appointmentId, PreVisitQuestionnaire::VERSION, PortalQuestionnaire::NOT_STARTED, null, null, $editable, false);
        }

        $answers = $questionnaire->answers();

        return new PortalQuestionnaire(
            $query->appointmentId,
            $questionnaire->version(),
            RequestStatus::CLOSED === $questionnaire->status() ? PortalQuestionnaire::CLOSED : PortalQuestionnaire::SUBMITTED,
            new QuestionnaireAnswersDetails(
                array_map(static fn (Symptom $symptom): string => $symptom->value, $answers->symptoms()),
                $answers->hasOngoingTreatment(),
                $answers->ongoingTreatmentDetails(),
                $answers->temperament()->value,
                $answers->notes(),
            ),
            $questionnaire->updatedAt()->setTimezone(new \DateTimeZone($this->clinics->clinicOf($query->clinicId)->timeZone)),
            $editable,
            $answers->mayBite(),
        );
    }
}
