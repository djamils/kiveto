<?php

declare(strict_types=1);

namespace App\Client\Application\Query\GetClientById;

final readonly class ContactMethodDto
{
    public function __construct(
        public string $type,
        public string $label,
        public string $value,
        public bool $isPrimary,
    ) {
    }
}
