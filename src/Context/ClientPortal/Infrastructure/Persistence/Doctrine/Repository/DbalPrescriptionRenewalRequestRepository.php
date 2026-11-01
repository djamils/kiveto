<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Request\PetCondition;
use App\Context\ClientPortal\Domain\Request\PortalRequestId;
use App\Context\ClientPortal\Domain\Request\PrescriptionRenewalRequest;
use App\Context\ClientPortal\Domain\Request\PrescriptionRenewalRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Context\ClientPortal\Domain\Request\RequestSubject;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * One row per request. MySQL generates `open_key` (the prescription while
 * submitted): the unique key `(clinic_id, open_key)` keeps a single open
 * request per prescription, even under concurrent submits.
 */
final readonly class DbalPrescriptionRenewalRequestRepository implements PrescriptionRenewalRequestRepositoryInterface
{
    public const string TABLE = 'client_portal__prescription_renewal_requests';

    private const array SUBJECTS = [
        RequestSubject::CLIENT->value       => 'client_id',
        RequestSubject::USER->value         => 'user_id',
        RequestSubject::PRESCRIPTION->value => 'prescription_id',
        RequestSubject::PET->value          => 'pet_id',
    ];

    public function __construct(private Connection $connection)
    {
    }

    public function add(PrescriptionRenewalRequest $request): bool
    {
        // A duplicate open key (or id) turns the insert into a no-op: 0 rows.
        return 1 === $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (id, clinic_id, client_id, user_id, prescription_id, pet_id, pet_condition, note, resulting_prescription_id,'
            . ' status, submitted_at, updated_at, resolved_at, resolved_by_user_id, resolved_by_name, resolution_message, notification_message_id,'
            . ' client_unreachable, closed_at, closed_reason)'
            . ' VALUES (:id, :clinic, :client, :user, :prescription, :pet, :condition, :note, :resulting,'
            . ' :status, :submitted, :updated, :resolved_at, :resolved_by_user_id, :resolved_by_name, :resolution_message, :message,'
            . ' :unreachable, :closed, :reason)'
            . ' ON DUPLICATE KEY UPDATE id = id',
            $this->params($request),
        );
    }

    public function save(PrescriptionRenewalRequest $request): void
    {
        $params = $this->params($request);
        unset($params['clinic'], $params['client'], $params['user'], $params['prescription'], $params['pet'], $params['condition'], $params['submitted']);

        $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET note = :note, resulting_prescription_id = :resulting, status = :status, updated_at = :updated,'
            . ' resolved_at = :resolved_at, resolved_by_user_id = :resolved_by_user_id, resolved_by_name = :resolved_by_name,'
            . ' resolution_message = :resolution_message, notification_message_id = :message, client_unreachable = :unreachable,'
            . ' closed_at = :closed, closed_reason = :reason WHERE id = :id',
            $params,
        );
    }

    public function findById(string $clinicId, string $requestId): ?PrescriptionRenewalRequest
    {
        return $this->one($clinicId, $requestId, '');
    }

    public function findByIdForUpdate(string $clinicId, string $requestId): ?PrescriptionRenewalRequest
    {
        return $this->one($clinicId, $requestId, ' FOR UPDATE');
    }

    public function findSubmittedForPrescription(string $clinicId, string $prescriptionId): ?PrescriptionRenewalRequest
    {
        return $this->open($clinicId, $prescriptionId, '');
    }

    public function findSubmittedForPrescriptionForUpdate(string $clinicId, string $prescriptionId): ?PrescriptionRenewalRequest
    {
        return $this->open($clinicId, $prescriptionId, ' FOR UPDATE');
    }

    public function findLatestRejected(string $clinicId, string $prescriptionId): ?PrescriptionRenewalRequest
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE clinic_id = :clinic AND prescription_id = :prescription AND status = :rejected'
            . ' ORDER BY resolved_at DESC, id DESC LIMIT 1',
            ['clinic' => SqlValues::binary($clinicId), 'prescription' => SqlValues::binary($prescriptionId), 'rejected' => RequestStatus::REJECTED->value],
        );

        return false === $row ? null : self::hydrate($row);
    }

    public function latestByPrescriptions(string $clinicId, array $prescriptionIds): array
    {
        $ids = array_values(array_filter($prescriptionIds, Uuid::isValid(...)));

        if ([] === $ids) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE clinic_id = :clinic AND prescription_id IN (:ids) ORDER BY submitted_at, id',
            ['clinic' => SqlValues::binary($clinicId), 'ids' => array_map(SqlValues::binary(...), $ids)],
            ['ids'    => ArrayParameterType::BINARY],
        );
        $latest = [];

        // Oldest first: the latest request of a prescription overwrites the earlier ones.
        foreach ($rows as $row) {
            $request                            = self::hydrate($row);
            $latest[$request->prescriptionId()] = $request;
        }

        return $latest;
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

    /**
     * @return array<string, string|int|null>
     */
    private function params(PrescriptionRenewalRequest $request): array
    {
        return [
            'id'           => SqlValues::binary($request->id()->toString()),
            'clinic'       => SqlValues::binary($request->clinicId()),
            'client'       => SqlValues::binary($request->clientId()),
            'user'         => SqlValues::binary($request->userId()),
            'prescription' => SqlValues::binary($request->prescriptionId()),
            'pet'          => SqlValues::binary($request->petId()),
            'condition'    => $request->petCondition()->value,
            'note'         => $request->note(),
            'resulting'    => SqlValues::nullableBinary($request->resultingPrescriptionId()),
            'status'       => $request->status()->value,
            'submitted'    => SqlValues::utc($request->submittedAt()),
            'updated'      => SqlValues::utc($request->updatedAt()),
            'message'      => SqlValues::nullableBinary($request->notificationMessageId()),
            'unreachable'  => (int) $request->isClientUnreachable(),
            'closed'       => SqlValues::nullableUtc($request->closedAt()),
            'reason'       => $request->closedReason()?->value,
        ] + RequestRows::resolution($request->resolution());
    }

    private function open(string $clinicId, string $prescriptionId, string $lock): ?PrescriptionRenewalRequest
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE clinic_id = :clinic AND open_key = :prescription' . $lock,
            ['clinic' => SqlValues::binary($clinicId), 'prescription' => SqlValues::binary($prescriptionId)],
        );

        return false === $row ? null : self::hydrate($row);
    }

    private function one(string $clinicId, string $requestId, string $lock): ?PrescriptionRenewalRequest
    {
        if (!Uuid::isValid($requestId)) {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE id = :id AND clinic_id = :clinic' . $lock,
            ['id' => SqlValues::binary($requestId), 'clinic' => SqlValues::binary($clinicId)],
        );

        return false === $row ? null : self::hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): PrescriptionRenewalRequest
    {
        $status = RequestStatus::from(RowAccessor::string($row, 'status'));

        return PrescriptionRenewalRequest::reconstitute(
            id: PortalRequestId::fromString(RowAccessor::uuid($row, 'id')),
            clinicId: RowAccessor::uuid($row, 'clinic_id'),
            clientId: RowAccessor::uuid($row, 'client_id'),
            userId: RowAccessor::uuid($row, 'user_id'),
            prescriptionId: RowAccessor::uuid($row, 'prescription_id'),
            petId: RowAccessor::uuid($row, 'pet_id'),
            petCondition: PetCondition::from(RowAccessor::string($row, 'pet_condition')),
            note: RowAccessor::nullableString($row, 'note'),
            resultingPrescriptionId: RowAccessor::nullableUuid($row, 'resulting_prescription_id'),
            status: $status,
            submittedAt: SqlValues::date(RowAccessor::string($row, 'submitted_at')),
            updatedAt: SqlValues::date(RowAccessor::string($row, 'updated_at')),
            resolution: RequestRows::hydrateResolution($row, $status),
            closedAt: SqlValues::nullableDate(RowAccessor::nullableString($row, 'closed_at')),
            closedReason: RequestRows::closedReason($row),
            notificationMessageId: RowAccessor::nullableUuid($row, 'notification_message_id'),
            clientUnreachable: RequestRows::flag($row, 'client_unreachable'),
        );
    }
}
