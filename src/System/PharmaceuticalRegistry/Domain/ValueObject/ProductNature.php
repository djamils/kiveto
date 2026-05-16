<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

enum ProductNature: string
{
    case CHEMICAL      = 'CHEMICAL';
    case IMMUNOLOGICAL = 'IMMUNOLOGICAL';
    case HOMEOPATHIC   = 'HOMEOPATHIC';
}
