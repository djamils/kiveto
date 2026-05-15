<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\ValueObject;

enum CustomerTaxStatus: string
{
    case B2C      = 'b2c';
    case B2B      = 'b2b';
    case INTRACOM = 'intracom';
    case EXPORT   = 'export';
}
