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
}
