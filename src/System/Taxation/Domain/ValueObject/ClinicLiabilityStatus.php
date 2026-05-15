<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\ValueObject;

enum ClinicLiabilityStatus: string
{
    case LIABLE     = 'liable';
    case NOT_LIABLE = 'not_liable';
}
