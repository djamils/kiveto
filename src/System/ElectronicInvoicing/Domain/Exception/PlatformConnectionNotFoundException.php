<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Exception;

final class PlatformConnectionNotFoundException extends \DomainException
{
    public function __construct(string $reference)
    {
        parent::__construct(\sprintf('Platform connection %s not found.', $reference));
    }
}
