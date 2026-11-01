<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Request;

interface PreVisitQuestionnaireRepositoryInterface
{
    public function save(PreVisitQuestionnaire $questionnaire): void;

    public function findByAppointment(string $clinicId, string $appointmentId): ?PreVisitQuestionnaire;

    /** The questionnaire of the appointment, locked until the transaction ends. */
    public function findByAppointmentForUpdate(string $clinicId, string $appointmentId): ?PreVisitQuestionnaire;

    /**
     * The questionnaires of the listed appointments, whatever their status.
     *
     * @param list<string> $appointmentIds
     *
     * @return list<PreVisitQuestionnaire>
     */
    public function findByAppointments(string $clinicId, array $appointmentIds): array;

    /**
     * The submitted questionnaires hanging on a subject, locked until the
     * transaction ends; a subject a questionnaire does not carry (a
     * prescription, a pet) has none. `clinicId` is ignored for a user.
     *
     * @return list<PreVisitQuestionnaire>
     */
    public function findSubmittedForSubjectForUpdate(RequestSubject $subject, string $subjectId, ?string $clinicId): array;

    /**
     * Every questionnaire an owner account answered, any clinic, oldest first.
     *
     * @return list<PreVisitQuestionnaire>
     */
    public function findByUser(string $userId): array;
}
