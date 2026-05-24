<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Article\ValueObject;

enum ArticleStatus: string
{
    case ACTIVE   = 'ACTIVE';
    case ARCHIVED = 'ARCHIVED';
}
