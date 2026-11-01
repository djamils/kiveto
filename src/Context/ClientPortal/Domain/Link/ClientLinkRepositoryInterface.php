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
     * The confirmed links of a user, oldest link first.
     *
     * @return list<ClientLink>
     */
    public function findConfirmedByUser(string $userId): array;

    /**
     * Every link of a user, whatever its status, by clinic then oldest first.
     *
     * @return list<ClientLink>
     */
    public function findByUser(string $userId): array;

    public function hasConfirmed(string $userId, string $clinicId): bool;
}
