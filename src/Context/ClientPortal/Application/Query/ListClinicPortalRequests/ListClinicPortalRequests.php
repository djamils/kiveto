<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListClinicPortalRequests;

use App\Shared\Application\Bus\QueryInterface;

/**
 * VetApp « Demandes Portal »: the clinic's pet change and renewal requests,
 * newest first; never closed ones nor questionnaires. Answers
 * `CursorPage<ClinicPortalRequest>`; `InvalidCursorException`.
 */
final readonly class ListClinicPortalRequests implements QueryInterface
{
    /**
     * @param ?string $status submitted|done|rejected, null for the three
     * @param ?string $kind   pet_change|prescription_renewal, null for both
     */
    public function __construct(
        public string $clinicId,
        public ?string $status = null,
        public ?string $kind = null,
        public ?string $cursor = null,
        public ?int $limit = null,
    ) {
    }
}
