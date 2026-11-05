<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Link;

interface ClientLinkRepositoryInterface
{
    public function save(ClientLink $link): void;

    /** The link of a user to a clinic client, locked until the transaction ends. */
    public function findForUpdate(string $userId, string $clinicId, string $clientId): ?ClientLink;

    /**
     * Every link of a clinic client, whatever its status.
     *
     * @return list<ClientLink>
     */
    public function findByClient(string $clinicId, string $clientId): array;

    /**
     * Every link of several clinic clients in one read, whatever their status.
     *
     * @param list<string> $clientIds
     *
     * @return list<ClientLink>
     */
    public function findByClients(string $clinicId, array $clientIds): array;

    /**
     * The confirmed links of a user, oldest link first.
     *
     * @return list<ClientLink>
     */
    public function findConfirmedByUser(string $userId): array;

    /**
     * The pending and confirmed links of a user, by clinic then oldest first.
     *
     * @return list<ClientLink>
     */
    public function findActiveByUser(string $userId): array;

    /**
     * The pending links of a clinic (link suggestions accepted by owners,
     * waiting for the clinic), oldest request first.
     *
     * @return list<ClientLink>
     */
    public function findPendingByClinic(string $clinicId): array;

    /**
     * Every link of a user, whatever its status, by clinic then oldest first.
     *
     * @return list<ClientLink>
     */
    public function findByUser(string $userId): array;

    public function hasConfirmed(string $userId, string $clinicId): bool;
}
