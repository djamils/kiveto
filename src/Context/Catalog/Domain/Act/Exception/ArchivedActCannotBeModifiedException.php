<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Act\Exception;

final class ArchivedActCannotBeModifiedException extends \DomainException
{
    public function __construct(string $actId)
    {
        parent::__construct(\sprintf('Act "%s" is archived and cannot be modified.', $actId));
    }
}
