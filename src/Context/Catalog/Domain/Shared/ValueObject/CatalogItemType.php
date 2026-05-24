<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Shared\ValueObject;

enum CatalogItemType: string
{
    case ACT     = 'ACT';
    case ARTICLE = 'ARTICLE';
}
