<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListQuestionnaireSummaries;

use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaire;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaireRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListQuestionnaireSummariesHandler
{
    public function __construct(
        private PreVisitQuestionnaireRepositoryInterface $questionnaires,
        private ClinicProfileInterface $clinics,
    ) {
    }

    /**
     * @return list<QuestionnaireSummary>
     */
    public function __invoke(ListQuestionnaireSummaries $query): array
    {
        $submitted = array_filter(
            $this->questionnaires->findByAppointments($query->clinicId, $query->appointmentIds),
            static fn (PreVisitQuestionnaire $questionnaire): bool => $questionnaire->isSubmitted(),
        );

        if ([] === $submitted) {
            return [];
        }

        $zone = new \DateTimeZone($this->clinics->clinicOf($query->clinicId)->timeZone);

        return array_values(array_map(static fn (PreVisitQuestionnaire $questionnaire): QuestionnaireSummary => new QuestionnaireSummary(
            $questionnaire->appointmentId(),
            $questionnaire->updatedAt()->setTimezone($zone),
            $questionnaire->answers()->mayBite(),
        ), $submitted));
    }
}
