<?php

declare(strict_types=1);

namespace App\Context\Patient\Domain\ValueObject;

enum PatientStatus: string
{
    case Active   = 'active';
    case Archived = 'archived';
}
