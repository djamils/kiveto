<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListConsultationRegister;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The clinic's register of consultations behind the « Consultations » page:
 * one page of rows, the counters of its header (in progress, to close, today)
 * and whether the clinic has recorded any consultation at all.
 *
 * Where the clinic's day starts is a display concern of the caller, so it
 * arrives as an instant: an open consultation started before it is « to
 * close », as is one whose visit is already over.
 *
 * Animal names, owners and invoices live in other contexts: the caller
 * resolves the search term to animal identifiers and the « still to collect »
 * filter to admission identifiers before asking.
 */
final readonly class ListConsultationRegister implements QueryInterface
{
    /**
     * @param list<string>  $textMatchAnimalIds     animals whose name or owner matched the search term
     * @param ?list<string> $restrictToAdmissionIds visits kept by a filter of another context,
     *                                              null when no such filter is active
     * @param ?list<string> $consultationIds        only these consultations (one sheet), null for all
     */
    public function __construct(
        public string $clinicId,
        public \DateTimeImmutable $dayStartUtc,
        public ?string $searchTerm = null,
        public array $textMatchAnimalIds = [],
        public ?\DateTimeImmutable $startedAfterUtc = null,
        public ?string $stage = null,
        public ?string $practitionerUserId = null,
        public ?string $kind = null,
        public ?array $restrictToAdmissionIds = null,
        public ?array $consultationIds = null,
        public int $page = 1,
        public int $limit = 25,
    ) {
    }
}
