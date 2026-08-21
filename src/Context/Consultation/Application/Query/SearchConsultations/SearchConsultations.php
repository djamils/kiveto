<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\SearchConsultations;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Clinic-wide, paginated consultation list behind the Consultations page.
 *
 * Patient, owner and species live in other contexts, so the caller resolves
 * them to animal identifiers first and passes those here; this context turns
 * them into patient identifiers through its own port. Two separate animal
 * lists are needed because they combine differently: the species filter
 * narrows the result set, while the free-text one widens the text match.
 *
 * The period filter arrives already resolved to an instant, because where the
 * day starts is a clinic display concern rather than a consultation rule.
 */
final readonly class SearchConsultations implements QueryInterface
{
    /**
     * @param list<string>  $textMatchAnimalIds  animals whose name or owner matched the search term
     * @param ?list<string> $restrictToAnimalIds animals kept by the species filter,
     *                                           null when no species filter is active
     * @param list<string>  $statuses            consultation statuses to keep, empty means all
     * @param list<string>  $practitionerUserIds practitioners to keep, empty means all
     * @param list<string>  $practitionerOrder   practitioner user ids in display order,
     *                                           used when sorting on the vet column
     */
    public function __construct(
        public string $clinicId,
        public ?string $searchTerm = null,
        public array $textMatchAnimalIds = [],
        public ?array $restrictToAnimalIds = null,
        public ?\DateTimeImmutable $startedAfterUtc = null,
        public array $statuses = [],
        public array $practitionerUserIds = [],
        public array $practitionerOrder = [],
        public string $sort = 'datetime',
        public string $direction = 'desc',
        public int $page = 1,
        public int $limit = 25,
    ) {
    }
}
