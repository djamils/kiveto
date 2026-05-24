<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Article\ValueObject;

enum ArticleKind: string
{
    case DRUG       = 'DRUG';
    case CONSUMABLE = 'CONSUMABLE';
    case FOOD       = 'FOOD';
    case EQUIPMENT  = 'EQUIPMENT';
}
