<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Exception;

final class UnknownCategoryPatternException extends \DomainException
{
    public function __construct(string $regimeId, string $pattern)
    {
        parent::__construct(\sprintf(
            'No active category matches pattern "%s" in regime "%s".',
            $pattern,
            $regimeId
        ));
    }
}
