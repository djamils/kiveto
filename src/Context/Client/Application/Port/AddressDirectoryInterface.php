<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Port;

/**
 * The national address directory (in France the Base Adresse Nationale and
 * the official list of communes by postal code), consulted while a client's
 * postal address is typed.
 */
interface AddressDirectoryInterface
{
    /**
     * Known addresses starting like the query, the closest to the given
     * postal code first when one is given. Empty when nothing matches or the
     * directory cannot be reached.
     *
     * @return list<AddressSuggestion>
     */
    public function suggest(string $query, int $limit, ?string $nearPostalCode = null): array;

    /**
     * The communes a postal code serves, alphabetically; an empty list for a
     * code that serves none, null when the directory cannot be reached.
     *
     * @return list<string>|null
     */
    public function communesOf(string $postalCode): ?array;
}
