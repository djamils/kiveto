<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListBillableLinesForAdmission;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListBillableLinesForAdmission implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $admissionId,
    ) {
    }
}
