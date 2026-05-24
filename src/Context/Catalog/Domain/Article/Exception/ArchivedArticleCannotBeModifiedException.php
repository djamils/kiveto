<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Article\Exception;

final class ArchivedArticleCannotBeModifiedException extends \DomainException
{
    public function __construct(string $articleId)
    {
        parent::__construct(\sprintf('Article "%s" is archived and cannot be modified.', $articleId));
    }
}
