<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Exception;

final class UnmatchedDeliveryNotFoundException extends \RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('Unmatched delivery "%s" not found.', $id));
    }
}
