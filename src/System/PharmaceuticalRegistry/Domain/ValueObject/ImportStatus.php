<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

enum ImportStatus: string
{
    case PENDING  = 'PENDING';
    case RUNNING  = 'RUNNING';
    case DIFFED   = 'DIFFED';
    case APPLYING = 'APPLYING';
    case APPLIED  = 'APPLIED';
    case FAILED   = 'FAILED';
}
