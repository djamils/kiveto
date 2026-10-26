<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Exception;

use App\System\ElectronicInvoicing\Domain\Connection\OnboardingState;

final class InvalidConnectionTransitionException extends \DomainException
{
    public function __construct(string $connectionId, OnboardingState $from, OnboardingState $to)
    {
        parent::__construct(\sprintf('Platform connection %s cannot go from %s to %s.', $connectionId, $from->value, $to->value));
    }
}
