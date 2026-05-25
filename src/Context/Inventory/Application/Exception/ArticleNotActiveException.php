<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Exception;

final class ArticleNotActiveException extends \DomainException
{
    public function __construct(string $articleId, string $clinicId)
    {
        parent::__construct(\sprintf('Article "%s" in clinic "%s" is not active.', $articleId, $clinicId));
    }
}
