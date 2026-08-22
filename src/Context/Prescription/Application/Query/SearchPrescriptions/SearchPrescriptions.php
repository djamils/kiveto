<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\SearchPrescriptions;

use App\Shared\Application\Bus\QueryInterface;

final readonly class SearchPrescriptions implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public SearchPrescriptionsCriteria $criteria,
    ) {
    }
}
