<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\ValueObject;

enum TransferStatus: string
{
    case NONE  = 'none';
    case SOLD  = 'sold';
    case GIVEN = 'given';
}
