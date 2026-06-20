<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\Supplier\Exception;

final class DuplicateSupplierCodeException extends \RuntimeException
{
    public function __construct(string $code)
    {
        parent::__construct(\sprintf('A supplier with code "%s" already exists.', $code));
    }
}
