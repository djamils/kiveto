<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Exception;

final class SwissCashRoundingRequiresChfException extends \DomainException
{
    public function __construct(string $currency)
    {
        parent::__construct(\sprintf('Swiss cash rounding requires CHF currency, got "%s".', $currency));
    }
}
