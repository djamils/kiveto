<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaire;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaireRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\RequestSubject;

final class InMemoryQuestionnaires implements PreVisitQuestionnaireRepositoryInterface
{
    /** @var array<string, PreVisitQuestionnaire> by "clinic/appointment" */
    public array $questionnaires = [];

    public int $saves = 0;

    public function save(PreVisitQuestionnaire $questionnaire): void
    {
        $this->questionnaires[$questionnaire->clinicId() . '/' . $questionnaire->appointmentId()] = $questionnaire;
        ++$this->saves;
    }

    public function findByAppointment(string $clinicId, string $appointmentId): ?PreVisitQuestionnaire
    {
        return $this->questionnaires[$clinicId . '/' . $appointmentId] ?? null;
    }

    public function findByAppointmentForUpdate(string $clinicId, string $appointmentId): ?PreVisitQuestionnaire
    {
        return $this->findByAppointment($clinicId, $appointmentId);
    }

    public function findByAppointments(string $clinicId, array $appointmentIds): array
    {
        return array_values(array_filter(
            $this->questionnaires,
            static fn (PreVisitQuestionnaire $q): bool => $q->clinicId() === $clinicId && \in_array($q->appointmentId(), $appointmentIds, true),
        ));
    }

    public function findSubmittedForSubjectForUpdate(RequestSubject $subject, string $subjectId, ?string $clinicId): array
    {
        return array_values(array_filter($this->questionnaires, static fn (PreVisitQuestionnaire $q): bool => $q->isSubmitted() && match ($subject) {
            RequestSubject::CLIENT      => $q->clientId() === $subjectId && $q->clinicId() === $clinicId,
            RequestSubject::USER        => $q->userId() === $subjectId,
            RequestSubject::APPOINTMENT => $q->appointmentId() === $subjectId && $q->clinicId() === $clinicId,
            default                     => false,
        }));
    }

    public function findByUser(string $userId): array
    {
        return array_values(array_filter($this->questionnaires, static fn (PreVisitQuestionnaire $q): bool => $q->userId() === $userId));
    }
}
