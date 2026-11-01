<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Request\PortalRequestId;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaire;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaireRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\QuestionnaireAnswers;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Context\ClientPortal\Domain\Request\RequestSubject;
use App\Context\ClientPortal\Domain\Request\Symptom;
use App\Context\ClientPortal\Domain\Request\Temperament;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * One row per appointment; the answers are a JSON document
 * `{symptoms, ongoingTreatment: {has, details}, temperament, notes}`.
 */
final readonly class DbalPreVisitQuestionnaireRepository implements PreVisitQuestionnaireRepositoryInterface
{
    public const string TABLE = 'client_portal__pre_visit_questionnaires';

    private const array SUBJECTS = [
        RequestSubject::CLIENT->value      => 'client_id',
        RequestSubject::USER->value        => 'user_id',
        RequestSubject::APPOINTMENT->value => 'appointment_id',
    ];

    public function __construct(private Connection $connection)
    {
    }

    public function save(PreVisitQuestionnaire $questionnaire): void
    {
        $answers = $questionnaire->answers();

        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (id, clinic_id, client_id, user_id, appointment_id, version, answers, status, submitted_at, updated_at, locked_at, closed_at, closed_reason)'
            . ' VALUES (:id, :clinic, :client, :user, :appointment, :version, :answers, :status, :submitted, :updated, :locked, :closed, :reason)'
            . ' AS new ON DUPLICATE KEY UPDATE user_id = new.user_id, version = new.version, answers = new.answers, status = new.status,'
            . ' updated_at = new.updated_at, locked_at = new.locked_at, closed_at = new.closed_at, closed_reason = new.closed_reason',
            [
                'id'          => SqlValues::binary($questionnaire->id()->toString()),
                'clinic'      => SqlValues::binary($questionnaire->clinicId()),
                'client'      => SqlValues::binary($questionnaire->clientId()),
                'user'        => SqlValues::binary($questionnaire->userId()),
                'appointment' => SqlValues::binary($questionnaire->appointmentId()),
                'version'     => $questionnaire->version(),
                'answers'     => json_encode([
                    'symptoms'         => array_map(static fn (Symptom $s): string => $s->value, $answers->symptoms()),
                    'ongoingTreatment' => ['has' => $answers->hasOngoingTreatment(), 'details' => $answers->ongoingTreatmentDetails()],
                    'temperament'      => $answers->temperament()->value,
                    'notes'            => $answers->notes(),
                ], \JSON_THROW_ON_ERROR),
                'status'    => $questionnaire->status()->value,
                'submitted' => SqlValues::utc($questionnaire->submittedAt()),
                'updated'   => SqlValues::utc($questionnaire->updatedAt()),
                'locked'    => SqlValues::nullableUtc($questionnaire->lockedAt()),
                'closed'    => SqlValues::nullableUtc($questionnaire->closedAt()),
                'reason'    => $questionnaire->closedReason()?->value,
            ],
        );
    }

    public function findByAppointment(string $clinicId, string $appointmentId): ?PreVisitQuestionnaire
    {
        return $this->one($clinicId, $appointmentId, '');
    }

    public function findByAppointmentForUpdate(string $clinicId, string $appointmentId): ?PreVisitQuestionnaire
    {
        return $this->one($clinicId, $appointmentId, ' FOR UPDATE');
    }

    public function findByAppointments(string $clinicId, array $appointmentIds): array
    {
        $ids = array_values(array_filter($appointmentIds, Uuid::isValid(...)));

        if ([] === $ids) {
            return [];
        }

        return array_map(self::hydrate(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE clinic_id = :clinic AND appointment_id IN (:ids)',
            ['clinic' => SqlValues::binary($clinicId), 'ids' => array_map(SqlValues::binary(...), $ids)],
            ['ids'    => ArrayParameterType::BINARY],
        ));
    }

    public function findSubmittedForSubjectForUpdate(RequestSubject $subject, string $subjectId, ?string $clinicId): array
    {
        $filter = RequestRows::subjectFilter(self::SUBJECTS, $subject, $subjectId, $clinicId);

        if (null === $filter) {
            return [];
        }

        return array_map(self::hydrate(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE ' . $filter[0] . ' ORDER BY submitted_at, id FOR UPDATE',
            $filter[1],
        ));
    }

    public function findByUser(string $userId): array
    {
        return array_map(self::hydrate(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE user_id = :user ORDER BY submitted_at, id',
            ['user' => SqlValues::binary($userId)],
        ));
    }

    private function one(string $clinicId, string $appointmentId, string $lock): ?PreVisitQuestionnaire
    {
        if (!Uuid::isValid($appointmentId)) {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE clinic_id = :clinic AND appointment_id = :appointment' . $lock,
            ['clinic' => SqlValues::binary($clinicId), 'appointment' => SqlValues::binary($appointmentId)],
        );

        return false === $row ? null : self::hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): PreVisitQuestionnaire
    {
        $answers   = RequestRows::json(RowAccessor::string($row, 'answers'));
        $treatment = $answers['ongoingTreatment'] ?? [];
        $symptoms  = $answers['symptoms'] ?? [];
        \assert(\is_array($treatment) && \is_array($symptoms));
        $details = $treatment['details'] ?? null;
        $notes   = $answers['notes'] ?? null;

        return PreVisitQuestionnaire::reconstitute(
            id: PortalRequestId::fromString(RowAccessor::uuid($row, 'id')),
            clinicId: RowAccessor::uuid($row, 'clinic_id'),
            clientId: RowAccessor::uuid($row, 'client_id'),
            userId: RowAccessor::uuid($row, 'user_id'),
            appointmentId: RowAccessor::uuid($row, 'appointment_id'),
            version: RowAccessor::int($row, 'version'),
            answers: QuestionnaireAnswers::reconstitute(
                array_values(array_map(static fn (mixed $s): Symptom => Symptom::from(\is_string($s) ? $s : ''), $symptoms)),
                true === ($treatment['has'] ?? false),
                \is_string($details) ? $details : null,
                Temperament::from(\is_string($answers['temperament'] ?? null) ? $answers['temperament'] : ''),
                \is_string($notes) ? $notes : null,
            ),
            status: RequestStatus::from(RowAccessor::string($row, 'status')),
            submittedAt: SqlValues::date(RowAccessor::string($row, 'submitted_at')),
            updatedAt: SqlValues::date(RowAccessor::string($row, 'updated_at')),
            lockedAt: SqlValues::nullableDate(RowAccessor::nullableString($row, 'locked_at')),
            closedAt: SqlValues::nullableDate(RowAccessor::nullableString($row, 'closed_at')),
            closedReason: RequestRows::closedReason($row),
        );
    }
}
