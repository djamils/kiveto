<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Article\Exception;

final class DuplicateGtinException extends \DomainException
{
    public function __construct(string $gtin)
    {
        parent::__construct(\sprintf('An article with GTIN "%s" already exists for this clinic.', $gtin));
    }
}
