<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

final readonly class ReportPatientSnapshot
{
    public function __construct(
        public ReportPatient $patient,
        public ?ReportOwner $owner,
    ) {
    }
}
