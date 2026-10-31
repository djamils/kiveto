<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListConsultationIdsForAdmission;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The consultations of one visit (admission), oldest first. Public: the
 * Client Portal ties an invoice to its visit when there is exactly one.
 * Answers list<string>; an unknown or malformed admission answers [].
 */
final readonly class ListConsultationIdsForAdmission implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $admissionId,
    ) {
    }
}
