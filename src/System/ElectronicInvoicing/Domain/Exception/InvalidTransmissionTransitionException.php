<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Exception;

use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionState;

final class InvalidTransmissionTransitionException extends \DomainException
{
    public function __construct(string $transmissionId, TransmissionState $from, TransmissionState $to)
    {
        parent::__construct(\sprintf('Transmission %s cannot go from %s to %s.', $transmissionId, $from->value, $to->value));
    }
}
