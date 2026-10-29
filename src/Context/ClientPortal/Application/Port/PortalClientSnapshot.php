<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

use App\Shared\Domain\ValueObject\PostalAddress;

final readonly class PortalClientSnapshot
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?PostalAddress $address,
    ) {
    }
}
