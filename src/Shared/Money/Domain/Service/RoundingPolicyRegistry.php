<?php

declare(strict_types=1);

namespace App\Shared\Money\Domain\Service;

use App\Shared\Money\Domain\RoundingPolicy\AccountingRounding;
use App\Shared\Money\Domain\RoundingPolicy\CommercialRounding;
use App\Shared\Money\Domain\RoundingPolicy\RoundingPolicy;
use App\Shared\Money\Domain\RoundingPolicy\SwissCashRounding;
use App\Shared\Money\Domain\ValueObject\RoundingPolicyId;

/**
 * Factory for rounding policies. Instantiates and returns the requested strategy.
 *
 * Stateless; can be injected as a singleton service or instantiated on the fly.
 */
final class RoundingPolicyRegistry
{
    public function get(RoundingPolicyId $id): RoundingPolicy
    {
        return match ($id) {
            RoundingPolicyId::ACCOUNTING => $this->accounting(),
            RoundingPolicyId::COMMERCIAL => $this->commercial(),
            RoundingPolicyId::SWISS_CASH => $this->swissCash(),
        };
    }

    public function accounting(): AccountingRounding
    {
        return new AccountingRounding();
    }

    public function commercial(): CommercialRounding
    {
        return new CommercialRounding();
    }

    public function swissCash(): SwissCashRounding
    {
        return new SwissCashRounding();
    }
}
