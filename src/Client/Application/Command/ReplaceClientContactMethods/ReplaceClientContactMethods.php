<?php

declare(strict_types=1);

namespace App\Client\Application\Command\ReplaceClientContactMethods;

final readonly class ReplaceClientContactMethods
{
    /**
     * @param list<ContactMethodDto> $contactMethods
     */
    public function __construct(
        public string $clinicId,
        public string $clientId,
        public array $contactMethods,
    ) {
    }
}
