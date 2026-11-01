<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListBookablePractitioners;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The practitioners a client may book online for a motif: those the motif
 * names who are still eligible in the clinic, by name → list<BookablePractitioner>,
 * or null when the motif is unknown, archived or not open online — or, for
 * a `newClient` (the public directory), not open to new clients.
 */
final readonly class ListBookablePractitioners implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $motifId,
        public bool $newClient = false,
    ) {
    }
}
