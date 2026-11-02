<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Booking\BookingRequest;
use App\Context\ClientPortal\Domain\Booking\BookingRequestId;
use App\Context\ClientPortal\Domain\Booking\BookingRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Booking\BookingRequestStatus;
use App\Context\ClientPortal\Domain\Booking\DeclineReason;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Uuid;

/** One row per new-client booking request; dates are written in UTC. */
final readonly class DbalBookingRequestRepository implements BookingRequestRepositoryInterface
{
    public const string TABLE = 'client_portal__booking_requests';

    public function __construct(private Connection $connection)
    {
    }

    public function save(BookingRequest $request): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (id, user_id, clinic_id, listing_id, appointment_id, motif_label, start_at, new_pet_name, new_pet_species,'
            . ' requester_first_name, requester_last_name, requester_phone, status, decline_reason, client_id, created_at, resolved_at, resolved_by_user_id, resolved_by_name)'
            . ' VALUES (:id, :user, :clinic, :listing, :appointment, :motif, :start, :petName, :petSpecies, :firstName, :lastName, :phone, :status, :reason, :client, :created, :resolved, :resolvedBy, :resolvedByName)'
            . ' AS new ON DUPLICATE KEY UPDATE status = new.status, decline_reason = new.decline_reason, client_id = new.client_id, resolved_at = new.resolved_at,'
            . ' resolved_by_user_id = new.resolved_by_user_id, resolved_by_name = new.resolved_by_name',
            [
                'id'             => SqlValues::binary($request->id()->toString()),
                'user'           => SqlValues::binary($request->userId()),
                'clinic'         => SqlValues::binary($request->clinicId()),
                'listing'        => SqlValues::binary($request->listingId()),
                'appointment'    => SqlValues::binary($request->appointmentId()),
                'motif'          => $request->motifLabel(),
                'start'          => SqlValues::utc($request->startAt()),
                'petName'        => $request->newPetName(),
                'petSpecies'     => $request->newPetSpecies(),
                'firstName'      => $request->requesterFirstName(),
                'lastName'       => $request->requesterLastName(),
                'phone'          => $request->requesterPhone(),
                'status'         => $request->status()->value,
                'reason'         => $request->declineReason()?->value,
                'client'         => SqlValues::nullableBinary($request->clientId()),
                'created'        => SqlValues::utc($request->createdAt()),
                'resolved'       => SqlValues::nullableUtc($request->resolvedAt()),
                'resolvedBy'     => SqlValues::nullableBinary($request->resolvedByUserId()),
                'resolvedByName' => $request->resolvedByName(),
            ],
        );
    }

    public function findInClinic(string $clinicId, string $requestId): ?BookingRequest
    {
        return $this->one($clinicId, $requestId, '');
    }

    public function findOfUser(string $userId, string $requestId): ?BookingRequest
    {
        if (!Uuid::isValid($requestId)) {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE id = :id AND user_id = :user',
            ['id' => SqlValues::binary($requestId), 'user' => SqlValues::binary($userId)],
        );

        return false === $row ? null : self::hydrate($row);
    }

    public function findInClinicForUpdate(string $clinicId, string $requestId): ?BookingRequest
    {
        return $this->one($clinicId, $requestId, ' FOR UPDATE');
    }

    public function findByAppointmentForUpdate(string $appointmentId): ?BookingRequest
    {
        if (!Uuid::isValid($appointmentId)) {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE appointment_id = :appointment FOR UPDATE',
            ['appointment' => SqlValues::binary($appointmentId)],
        );

        return false === $row ? null : self::hydrate($row);
    }

    public function findPendingByUserForUpdate(string $userId): array
    {
        return array_map(self::hydrate(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE user_id = :user AND status = :pending ORDER BY created_at ASC, id ASC FOR UPDATE',
            ['user' => SqlValues::binary($userId), 'pending' => BookingRequestStatus::PENDING->value],
        ));
    }

    public function findPageByUser(string $userId, ?\DateTimeImmutable $afterCreatedAt, ?string $afterId, int $limit): array
    {
        $where  = 'user_id = :user';
        $params = ['user' => SqlValues::binary($userId), 'limit' => $limit];

        if (null !== $afterCreatedAt && null !== $afterId) {
            $where .= ' AND (created_at < :at OR (created_at = :at AND id < :after))';
            $params['at']    = SqlValues::utc($afterCreatedAt);
            $params['after'] = SqlValues::binary($afterId);
        }

        return array_map(self::hydrate(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE ' . $where . ' ORDER BY created_at DESC, id DESC LIMIT :limit',
            $params,
            ['limit' => ParameterType::INTEGER],
        ));
    }

    public function findByUser(string $userId): array
    {
        return array_map(self::hydrate(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE user_id = :user ORDER BY created_at ASC, id ASC',
            ['user' => SqlValues::binary($userId)],
        ));
    }

    public function findPendingByClinic(string $clinicId): array
    {
        return array_map(self::hydrate(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE clinic_id = :clinic AND status = :pending ORDER BY created_at ASC, id ASC',
            ['clinic' => SqlValues::binary($clinicId), 'pending' => BookingRequestStatus::PENDING->value],
        ));
    }

    public function findDecidedByClinic(string $clinicId, int $limit): array
    {
        return array_map(self::hydrate(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE clinic_id = :clinic AND status <> :pending ORDER BY resolved_at DESC, id DESC LIMIT :limit',
            ['clinic' => SqlValues::binary($clinicId), 'pending' => BookingRequestStatus::PENDING->value, 'limit' => $limit],
            ['limit'  => ParameterType::INTEGER],
        ));
    }

    public function scrubUser(string $userId): void
    {
        $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET requester_first_name = NULL, requester_last_name = NULL, requester_phone = NULL, new_pet_name = NULL, new_pet_species = NULL'
            . ' WHERE user_id = :user',
            ['user' => SqlValues::binary($userId)],
        );
    }

    private function one(string $clinicId, string $requestId, string $lock): ?BookingRequest
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
    private static function hydrate(array $row): BookingRequest
    {
        $reason = RowAccessor::nullableString($row, 'decline_reason');

        return BookingRequest::reconstitute(
            id: BookingRequestId::fromString(RowAccessor::uuid($row, 'id')),
            userId: RowAccessor::uuid($row, 'user_id'),
            clinicId: RowAccessor::uuid($row, 'clinic_id'),
            listingId: RowAccessor::uuid($row, 'listing_id'),
            appointmentId: RowAccessor::uuid($row, 'appointment_id'),
            motifLabel: RowAccessor::string($row, 'motif_label'),
            startAt: SqlValues::date(RowAccessor::string($row, 'start_at')),
            newPetName: RowAccessor::nullableString($row, 'new_pet_name'),
            newPetSpecies: RowAccessor::nullableString($row, 'new_pet_species'),
            requesterFirstName: RowAccessor::nullableString($row, 'requester_first_name'),
            requesterLastName: RowAccessor::nullableString($row, 'requester_last_name'),
            requesterPhone: RowAccessor::nullableString($row, 'requester_phone'),
            status: BookingRequestStatus::from(RowAccessor::string($row, 'status')),
            declineReason: null === $reason ? null : DeclineReason::from($reason),
            clientId: RowAccessor::nullableUuid($row, 'client_id'),
            createdAt: SqlValues::date(RowAccessor::string($row, 'created_at')),
            resolvedAt: SqlValues::nullableDate(RowAccessor::nullableString($row, 'resolved_at')),
            resolvedByUserId: RowAccessor::nullableUuid($row, 'resolved_by_user_id'),
            resolvedByName: RowAccessor::nullableString($row, 'resolved_by_name'),
        );
    }
}
