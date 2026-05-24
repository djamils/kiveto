<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Act\Exception;

final class DuplicateActCodeException extends \DomainException
{
    public function __construct(string $code)
    {
        parent::__construct(\sprintf('An act with code "%s" already exists for this clinic.', $code));
    }
}
