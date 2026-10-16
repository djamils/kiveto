<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListCommunesForPostalCode;

use App\Context\Client\Application\Port\AddressDirectoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListCommunesForPostalCodeHandler
{
    public function __construct(
        private AddressDirectoryInterface $addressDirectory,
    ) {
    }

    /**
     * @return list<string>|null null when the directory cannot be reached
     */
    public function __invoke(ListCommunesForPostalCode $query): ?array
    {
        // A French postal code has five digits: anything else serves no commune.
        if (1 !== preg_match('/^\d{5}$/', $query->postalCode)) {
            return [];
        }

        return $this->addressDirectory->communesOf($query->postalCode);
    }
}
