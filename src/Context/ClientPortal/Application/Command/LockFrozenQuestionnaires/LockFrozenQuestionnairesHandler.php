<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\LockFrozenQuestionnaires;

use App\Context\ClientPortal\Application\Port\OwnerAppointment;
use App\Context\ClientPortal\Application\Service\QuestionnaireAccess;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaireRepositoryInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Open questionnaires only exist for upcoming visits (a started one gets
 * locked here), so the scan stays small; at most BATCH a run, the next run
 * catches up. An appointment Scheduling no longer knows is left alone.
 */
#[AsMessageHandler]
final readonly class LockFrozenQuestionnairesHandler
{
    public const int BATCH = 2000;

    public function __construct(
        private PreVisitQuestionnaireRepositoryInterface $questionnaires,
        private QuestionnaireAccess $access,
        private ClockInterface $clock,
    ) {
    }

    /** @return int how many questionnaires were locked */
    public function __invoke(LockFrozenQuestionnaires $command): int
    {
        /** @var array<string, list<OwnerAppointment>> $byClinic */
        $byClinic = [];

        foreach ($this->questionnaires->findUnlocked(self::BATCH) as $questionnaire) {
            $appointment = $this->access->appointment($questionnaire->clinicId(), $questionnaire->appointmentId());

            if (null !== $appointment) {
                $byClinic[$questionnaire->clinicId()][] = $appointment;
            }
        }

        $now    = $this->clock->now();
        $locked = 0;

        foreach ($byClinic as $clinicId => $appointments) {
            foreach ($this->access->frozen($clinicId, $appointments) as $appointmentId) {
                $this->questionnaires->recordLock($clinicId, $appointmentId, $now);
                ++$locked;
            }
        }

        return $locked;
    }
}
