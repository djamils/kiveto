<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetReportShareState;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The state of « Partager sur le Portal » for a consultation of the clinic.
 * Answers a ReportShareState.
 */
final readonly class GetReportShareState implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $consultationId,
    ) {
    }
}
