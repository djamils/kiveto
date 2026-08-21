<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\Exception;

final class DuplicateMedicalAlertException extends \DomainException
{
    public function __construct(string $animalId, string $label)
    {
        parent::__construct(\sprintf('Animal "%s" already carries the medical alert "%s".', $animalId, $label));
    }
}
