<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\Exception;

final class StrayCustodyNotFoundException extends \DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('StrayCustody "%s" not found.', $id));
    }
}
