<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\SubmitQuestionnaire;

use App\Context\ClientPortal\Application\Service\QuestionnaireAccess;
use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;
use App\Context\ClientPortal\Domain\Request\PortalRequestId;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaire;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaireRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\QuestionnaireAnswers;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Ownership first (404), then the answers (422), then the lock: a frozen
 * questionnaire records its lock (a later reschedule never reopens it) and
 * answers LOCKED without touching the answers.
 */
#[AsMessageHandler]
final readonly class SubmitQuestionnaireHandler
{
    public function __construct(
        private QuestionnaireAccess $access,
        private PreVisitQuestionnaireRepositoryInterface $questionnaires,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SubmitQuestionnaire $command): SubmitQuestionnaireOutcome
    {
        $owned = $this->access->ownedAppointment($command->userId, $command->clinicId, $command->appointmentId);

        if (PreVisitQuestionnaire::VERSION !== $command->version) {
            throw new InvalidPortalRequestException('version', \sprintf('Version de questionnaire non prise en charge : %d.', $command->version));
        }

        $answers       = QuestionnaireAnswers::of($command->symptoms, $command->hasOngoingTreatment, $command->ongoingTreatmentDetails, $command->temperament, $command->notes);
        $questionnaire = $this->questionnaires->findByAppointmentForUpdate($command->clinicId, $command->appointmentId);
        $now           = $this->clock->now();

        if (!$this->access->isOpen($command->clinicId, $owned->appointment, $questionnaire)) {
            if (null !== $questionnaire && $questionnaire->lock($now)) {
                $this->questionnaires->save($questionnaire);
            }

            return SubmitQuestionnaireOutcome::LOCKED;
        }

        if (null === $questionnaire) {
            $questionnaire = PreVisitQuestionnaire::submit(
                PortalRequestId::fromString($this->uuids->generate()),
                $command->clinicId,
                $owned->clientId,
                $command->userId,
                $command->appointmentId,
                $command->version,
                $answers,
                $now,
            );
        } else {
            $questionnaire->answer($command->version, $command->userId, $answers, $now);
        }

        $this->questionnaires->save($questionnaire);

        return SubmitQuestionnaireOutcome::SUBMITTED;
    }
}
