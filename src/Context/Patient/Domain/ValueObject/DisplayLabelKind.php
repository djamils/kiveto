<?php

declare(strict_types=1);

namespace App\Context\Patient\Domain\ValueObject;

enum DisplayLabelKind: string
{
    case Provisional = 'provisional';
    case FromAnimal  = 'from_animal';
    case Custom      = 'custom';
}
