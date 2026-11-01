<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListBookableMotifs;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The motifs the owner may book online in the clinic, in the clinic's
 * order → list<BookableMotif>. With a pet, a motif is suggested when the
 * pet has an overdue or due-soon care item of the motif's kind; a pet the
 * account does not see throws PetNotFoundException.
 */
final readonly class ListBookableMotifs implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
        public ?string $petId = null,
    ) {
    }
}
