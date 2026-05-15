<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Exception;

final class EmptyTaxRegimeCannotBeActivatedException extends \DomainException
{
    public function __construct(string $regimeId)
    {
        parent::__construct(\sprintf(
            'Tax regime "%s" cannot be activated because it has no rates.',
            $regimeId
        ));
    }
}
