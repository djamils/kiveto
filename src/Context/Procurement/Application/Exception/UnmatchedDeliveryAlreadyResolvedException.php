<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Exception;

final class UnmatchedDeliveryAlreadyResolvedException extends \RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('Unmatched delivery "%s" has already been resolved.', $id));
    }
}
