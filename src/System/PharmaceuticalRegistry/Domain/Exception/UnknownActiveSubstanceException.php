<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Exception;

final class UnknownActiveSubstanceException extends \DomainException
{
    public function __construct(string $label)
    {
        parent::__construct(\sprintf('Active substance not found for label: "%s".', $label));
    }
}
