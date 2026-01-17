<?php

declare(strict_types=1);

namespace App\Client\Application\Command\CreateClient;

final readonly class CreateClient
{
    /**
     * @param list<ContactMethodDto> $contactMethods
     */
    public function __construct(
        public string $clinicId,
        public string $firstName,
        public string $lastName,
        public array $contactMethods,
    ) {
    }
}
