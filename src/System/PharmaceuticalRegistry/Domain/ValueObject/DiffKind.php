<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

enum DiffKind: string
{
    case CREATE   = 'CREATE';
    case UPDATE   = 'UPDATE';
    case WITHDRAW = 'WITHDRAW';
}
