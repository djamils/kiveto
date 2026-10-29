<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * The issued invoice of the visit a consultation belongs to.
 */
final readonly class ConsultationInvoice
{
    public function __construct(
        public string $id,
        public string $number,
        public int $totalGrossMinorUnits,
    ) {
    }
}
