<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** The animals of a client (Animal context). */
interface PetNamesInterface
{
    /**
     * Names of the active animals the client is the primary owner of, in
     * alphabetical order.
     *
     * @return list<string>
     */
    public function petNamesOf(string $clinicId, string $clientId): array;

    /**
     * The same for several clients in one read, by client id; a client
     * without such animals is left out.
     *
     * @param list<string> $clientIds
     *
     * @return array<string, list<string>>
     */
    public function petNamesOfClients(string $clinicId, array $clientIds): array;
}
