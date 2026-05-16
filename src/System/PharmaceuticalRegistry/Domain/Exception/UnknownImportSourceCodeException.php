<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Exception;

final class UnknownImportSourceCodeException extends \DomainException
{
    public function __construct(string $code)
    {
        parent::__construct(\sprintf('Unknown import source code: "%s".', $code));
    }
}
