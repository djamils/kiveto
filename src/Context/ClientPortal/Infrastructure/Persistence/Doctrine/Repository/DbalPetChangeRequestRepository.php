<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Request\ChangeField;
use App\Context\ClientPortal\Domain\Request\PetChangeRequest;
use App\Context\ClientPortal\Domain\Request\PetChangeRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\PortalRequestId;
use App\Context\ClientPortal\Domain\Request\ProposedChange;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Context\ClientPortal\Domain\Request\RequestSubject;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/** One row per request; the normalised value is a JSON object. */
final readonly class DbalPetChangeRequestRepository implements PetChangeRequestRepositoryInterface
{
    public const string TABLE = 'client_portal__pet_change_requests';

    private const array SUBJECTS = [
        RequestSubject::CLIENT->value => 'client_id',
        RequestSubject::USER->value   => 'user_id',
        RequestSubject::PET->value    => 'pet_id',
    ];

    public function __construct(private Connection $connection)
    {
    }

    public function save(PetChangeRequest $request): void
    {
        $change = $request->change();

        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (id, clinic_id, client_id, user_id, pet_id, field, proposed_value, normalised_value, current_value_at_submit, note,'
            . ' status, submitted_at, updated_at, resolved_at, resolved_by_user_id, resolved_by_name, resolution_message, notification_message_id,'
            . ' client_unreachable, closed_at, closed_reason)'
            . ' VALUES (:id, :clinic, :client, :user, :pet, :field, :proposed, :normalised, :current, :note,'
            . ' :status, :submitted, :updated, :resolved_at, :resolved_by_user_id, :resolved_by_name, :resolution_message, :message,'
            . ' :unreachable, :closed, :reason)'
            . ' AS new ON DUPLICATE KEY UPDATE proposed_value = new.proposed_value, note = new.note, status = new.status, updated_at = new.updated_at,'
            . ' resolved_at = new.resolved_at, resolved_by_user_id = new.resolved_by_user_id, resolved_by_name = new.resolved_by_name,'
            . ' resolution_message = new.resolution_message, notification_message_id = new.notification_message_id,'
            . ' client_unreachable = new.client_unreachable, closed_at = new.closed_at, closed_reason = new.closed_reason',
            [
                'id'          => SqlValues::binary($request->id()->toString()),
                'clinic'      => SqlValues::binary($request->clinicId()),
                'client'      => SqlValues::binary($request->clientId()),
                'user'        => SqlValues::binary($request->userId()),
                'pet'         => SqlValues::binary($request->petId()),
                'field'       => $change->field()->value,
                'proposed'    => $change->proposedValue(),
                'normalised'  => json_encode((object) $change->normalised(), \JSON_THROW_ON_ERROR),
                'current'     => $request->currentValueAtSubmit(),
                'note'        => $change->note(),
                'status'      => $request->status()->value,
                'submitted'   => SqlValues::utc($request->submittedAt()),
                'updated'     => SqlValues::utc($request->updatedAt()),
                'message'     => SqlValues::nullableBinary($request->notificationMessageId()),
                'unreachable' => (int) $request->isClientUnreachable(),
                'closed'      => SqlValues::nullableUtc($request->closedAt()),
                'reason'      => $request->closedReason()?->value,
            ] + RequestRows::resolution($request->resolution()),
        );
    }

    public function findById(string $clinicId, string $requestId): ?PetChangeRequest
    {
        return $this->one($clinicId, $requestId, '');
    }

    public function findByIdForUpdate(string $clinicId, string $requestId): ?PetChangeRequest
    {
        return $this->one($clinicId, $requestId, ' FOR UPDATE');
    }

    public function countSubmitted(string $clinicId, string $petId, ChangeField $field): int
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE clinic_id = :clinic AND pet_id = :pet AND field = :field AND status = :submitted',
            ['clinic' => SqlValues::binary($clinicId), 'pet' => SqlValues::binary($petId), 'field' => $field->value, 'submitted' => RequestStatus::SUBMITTED->value],
        );
        \assert(is_numeric($count));

        return (int) $count;
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

    private function one(string $clinicId, string $requestId, string $lock): ?PetChangeRequest
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
    private static function hydrate(array $row): PetChangeRequest
    {
        $status     = RequestStatus::from(RowAccessor::string($row, 'status'));
        $normalised = array_filter(RequestRows::json(RowAccessor::string($row, 'normalised_value')), static fn (mixed $v): bool => \is_string($v) || \is_int($v));

        return PetChangeRequest::reconstitute(
            id: PortalRequestId::fromString(RowAccessor::uuid($row, 'id')),
            clinicId: RowAccessor::uuid($row, 'clinic_id'),
            clientId: RowAccessor::uuid($row, 'client_id'),
            userId: RowAccessor::uuid($row, 'user_id'),
            petId: RowAccessor::uuid($row, 'pet_id'),
            change: ProposedChange::reconstitute(
                ChangeField::from(RowAccessor::string($row, 'field')),
                RowAccessor::string($row, 'proposed_value'),
                $normalised,
                RowAccessor::nullableString($row, 'note'),
            ),
            currentValueAtSubmit: RowAccessor::nullableString($row, 'current_value_at_submit'),
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
