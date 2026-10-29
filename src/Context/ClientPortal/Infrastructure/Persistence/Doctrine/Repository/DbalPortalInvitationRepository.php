<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Invitation\InvitationChannel;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitation;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationId;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationRepositoryInterface;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationStatus;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;

/**
 * Raw SQL so that the locking reads (`FOR UPDATE`) of the acceptance and of
 * the resend are explicit. Dates are written in UTC.
 */
final readonly class DbalPortalInvitationRepository implements PortalInvitationRepositoryInterface
{
    private const string TABLE   = 'client_portal__portal_invitations';
    private const string PENDING = PortalInvitationStatus::PENDING->value;

    public function __construct(private Connection $connection)
    {
    }

    public function save(PortalInvitation $invitation): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE
            . ' (id, clinic_id, client_id, phone, email, channel, token_hash, status, sent_at, expires_at, accepted_at, accepted_by_user_id, revoked_at)'
            . ' VALUES (:id, :clinic, :client, :phone, :email, :channel, :hash, :status, :sent, :expires, :accepted, :acceptedBy, :revoked)'
            . ' AS new ON DUPLICATE KEY UPDATE phone = new.phone, email = new.email, channel = new.channel, token_hash = new.token_hash,'
            . ' status = new.status, sent_at = new.sent_at, expires_at = new.expires_at, accepted_at = new.accepted_at,'
            . ' accepted_by_user_id = new.accepted_by_user_id, revoked_at = new.revoked_at',
            [
                'id'         => SqlValues::binary($invitation->id()->toString()),
                'clinic'     => SqlValues::binary($invitation->clinicId()),
                'client'     => SqlValues::binary($invitation->clientId()),
                'phone'      => $invitation->phone()->toString(),
                'email'      => $invitation->email(),
                'channel'    => $invitation->channel()->value,
                'hash'       => $invitation->tokenHash(),
                'status'     => $invitation->status()->value,
                'sent'       => SqlValues::utc($invitation->sentAt()),
                'expires'    => SqlValues::utc($invitation->expiresAt()),
                'accepted'   => SqlValues::nullableUtc($invitation->acceptedAt()),
                'acceptedBy' => SqlValues::nullableBinary($invitation->acceptedByUserId()),
                'revoked'    => SqlValues::nullableUtc($invitation->revokedAt()),
            ],
        );
    }

    public function findByIdForUpdate(PortalInvitationId $id): ?PortalInvitation
    {
        return $this->one('id = :id FOR UPDATE', ['id' => SqlValues::binary($id->toString())]);
    }

    public function findByTokenHash(string $tokenHash): ?PortalInvitation
    {
        return $this->one('token_hash = :hash', ['hash' => $tokenHash]);
    }

    public function findByTokenHashForUpdate(string $tokenHash): ?PortalInvitation
    {
        return $this->one('token_hash = :hash FOR UPDATE', ['hash' => $tokenHash]);
    }

    public function findPendingForClientForUpdate(string $clinicId, string $clientId): array
    {
        return $this->list(
            'clinic_id = :clinic AND client_id = :client AND status = :pending ORDER BY sent_at DESC, id DESC FOR UPDATE',
            ['clinic' => SqlValues::binary($clinicId), 'client' => SqlValues::binary($clientId), 'pending' => self::PENDING],
        );
    }

    public function findLatestForClient(string $clinicId, string $clientId): ?PortalInvitation
    {
        return $this->one(
            'clinic_id = :clinic AND client_id = :client ORDER BY sent_at DESC, id DESC LIMIT 1',
            ['clinic' => SqlValues::binary($clinicId), 'client' => SqlValues::binary($clientId)],
        );
    }

    public function findPendingByPhone(PhoneNumber $phone, \DateTimeImmutable $now): array
    {
        return $this->list(
            'phone = :phone AND status = :pending AND expires_at > :now ORDER BY sent_at ASC, id ASC',
            ['phone' => $phone->toString(), 'pending' => self::PENDING, 'now' => SqlValues::utc($now)],
        );
    }

    /**
     * @param array<string, string> $parameters
     */
    private function one(string $where, array $parameters): ?PortalInvitation
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM ' . self::TABLE . ' WHERE ' . $where, $parameters);

        return false === $row ? null : self::hydrate($row);
    }

    /**
     * @param array<string, string> $parameters
     *
     * @return list<PortalInvitation>
     */
    private function list(string $where, array $parameters): array
    {
        return array_map(
            self::hydrate(...),
            $this->connection->fetchAllAssociative('SELECT * FROM ' . self::TABLE . ' WHERE ' . $where, $parameters),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): PortalInvitation
    {
        return PortalInvitation::reconstitute(
            id: PortalInvitationId::fromString(RowAccessor::uuid($row, 'id')),
            clinicId: RowAccessor::uuid($row, 'clinic_id'),
            clientId: RowAccessor::uuid($row, 'client_id'),
            phone: PhoneNumber::fromString(RowAccessor::string($row, 'phone')),
            email: RowAccessor::nullableString($row, 'email'),
            channel: InvitationChannel::from(RowAccessor::string($row, 'channel')),
            tokenHash: RowAccessor::string($row, 'token_hash'),
            status: PortalInvitationStatus::from(RowAccessor::string($row, 'status')),
            sentAt: SqlValues::date(RowAccessor::string($row, 'sent_at')),
            expiresAt: SqlValues::date(RowAccessor::string($row, 'expires_at')),
            acceptedAt: SqlValues::nullableDate(RowAccessor::nullableString($row, 'accepted_at')),
            acceptedByUserId: RowAccessor::nullableUuid($row, 'accepted_by_user_id'),
            revokedAt: SqlValues::nullableDate(RowAccessor::nullableString($row, 'revoked_at')),
        );
    }
}
