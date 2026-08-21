<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetConsultationTotalsForAdmissions;

use App\Shared\Application\Bus\QueryInterface;

/**
 * What each visit owes, summed over its consultations.
 *
 * Feeds the checkout column of the Flux du jour, which shows the amount to
 * collect before the visit is closed.
 */
final readonly class GetConsultationTotalsForAdmissions implements QueryInterface
{
    /**
     * @param list<string> $admissionIds
     */
    public function __construct(
        public string $clinicId,
        public array $admissionIds,
    ) {
    }
}
