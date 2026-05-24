<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Article\Exception;

final class DuplicateArticleCodeException extends \DomainException
{
    public function __construct(string $code)
    {
        parent::__construct(\sprintf('An article with code "%s" already exists for this clinic.', $code));
    }
}
