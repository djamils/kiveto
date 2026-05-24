<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Article\Exception;

final class ArticleNotFoundException extends \DomainException
{
    public function __construct(string $articleId)
    {
        parent::__construct(\sprintf('Article "%s" not found.', $articleId));
    }
}
