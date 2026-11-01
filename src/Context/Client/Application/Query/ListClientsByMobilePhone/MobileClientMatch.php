<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientsByMobilePhone;

/** A client file carrying the mobile number asked. */
final readonly class MobileClientMatch
{
    /**
     * @param list<string> $animalNames active animals the client is the primary owner of, alphabetically
     */
    public function __construct(
        public string $clinicId,
        public string $clientId,
        public string $firstName,
        public string $lastName,
        public array $animalNames,
    ) {
    }
}
