<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Invitation;

use App\Shared\Domain\ValueObject\PhoneNumber;

/**
 * The `…ForUpdate` reads lock the rows they return (and, for the lists, the
 * index range they scan) until the transaction ends: they must run inside one.
 */
interface PortalInvitationRepositoryInterface
{
    public function save(PortalInvitation $invitation): void;

    public function findByIdForUpdate(PortalInvitationId $id): ?PortalInvitation;

    public function findByTokenHash(string $tokenHash): ?PortalInvitation;

    public function findByTokenHashForUpdate(string $tokenHash): ?PortalInvitation;

    /**
     * The pending invitations (expired ones included) of a clinic client,
     * newest first.
     *
     * @return list<PortalInvitation>
     */
    public function findPendingForClientForUpdate(string $clinicId, string $clientId): array;

    /** The most recently sent invitation of a clinic client, whatever its status. */
    public function findLatestForClient(string $clinicId, string $clientId): ?PortalInvitation;

    /**
     * The invitations of a phone that can still be accepted (pending, not
     * expired at `now`), oldest first.
     *
     * @return list<PortalInvitation>
     */
    public function findPendingByPhone(PhoneNumber $phone, \DateTimeImmutable $now): array;

    /**
     * Every pending invitation of a phone, expired ones included, oldest first.
     *
     * @return list<PortalInvitation>
     */
    public function findPendingByPhoneForUpdate(PhoneNumber $phone): array;
}
