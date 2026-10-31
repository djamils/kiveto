<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\ReleaseAwaitingDocuments;

use App\Shared\Application\Bus\CommandInterface;

/** A client's first confirmed link: their waiting documents get their files. */
final readonly class ReleaseAwaitingDocuments implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $clientId,
    ) {
    }
}
