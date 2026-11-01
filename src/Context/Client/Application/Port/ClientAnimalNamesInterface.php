<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Port;

/** The animals of client files, by name (Animal context). */
interface ClientAnimalNamesInterface
{
    /**
     * Names of the active animals each client is the primary owner of,
     * alphabetically; a client without any is absent.
     *
     * @param list<string> $clientIds
     *
     * @return array<string, list<string>> by client id
     */
    public function namesPerClient(string $clinicId, array $clientIds): array;
}
