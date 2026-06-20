<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Exception;

final class CancelledLineCannotReceiveException extends \RuntimeException
{
    public function __construct(string $lineId)
    {
        parent::__construct(\sprintf('Cannot receive goods for cancelled line "%s".', $lineId));
    }
}
