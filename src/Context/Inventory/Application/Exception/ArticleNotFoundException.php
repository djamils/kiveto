<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Exception;

final class ArticleNotFoundException extends \DomainException
{
    public function __construct(string $articleId, string $clinicId)
    {
        parent::__construct(\sprintf('Article "%s" not found in clinic "%s".', $articleId, $clinicId));
    }
}
