<?php

declare(strict_types=1);

namespace App\Context\Admission\Domain\ValueObject;

enum AdmissionStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
}
