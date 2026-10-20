<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Exception;

final class UnparsableLifecycleMessageException extends \RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct(\sprintf('Unparsable lifecycle message: %s', $reason));
    }
}
