<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Exception;

final class TransmissionNotFoundException extends \DomainException
{
    public function __construct(string $reference)
    {
        parent::__construct(\sprintf('Outbound transmission %s not found.', $reference));
    }
}
