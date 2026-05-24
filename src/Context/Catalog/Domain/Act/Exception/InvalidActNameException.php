<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Act\Exception;

final class InvalidActNameException extends \DomainException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
