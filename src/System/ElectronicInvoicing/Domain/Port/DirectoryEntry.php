<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Port;

/**
 * What the platform directory knows of a company: whether it can receive
 * electronic invoices, and at which address.
 */
final readonly class DirectoryEntry
{
    public function __construct(
        public bool $reachable,
        public ?string $scheme,
        public ?string $identifier,
    ) {
    }
}
