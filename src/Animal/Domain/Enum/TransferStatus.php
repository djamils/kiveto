<?php

declare(strict_types=1);

namespace App\Animal\Domain\Enum;

enum TransferStatus: string
{
    case NONE  = 'none';
    case SOLD  = 'sold';
    case GIVEN = 'given';
}
