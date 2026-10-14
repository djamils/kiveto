<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListDirectoryMatches;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Animals — and their primary owners — matching the directory's facets.
 *
 * Unpaginated identifiers on purpose: the directory narrows its own paginated
 * client or animal query with them. The free text covers the animal's name,
 * breed, owner name and chip number.
 */
final readonly class ListDirectoryMatches implements QueryInterface
{
    /**
     * @param list<string> $species species to keep, empty means all
     */
    public function __construct(
        public string $clinicId,
        public ?string $searchTerm = null,
        public array $species = [],
        public bool $withMedicalAlert = false,
        public bool $withDueReminder = false,
        public ?\DateTimeImmutable $asOf = null,
    ) {
    }
}
