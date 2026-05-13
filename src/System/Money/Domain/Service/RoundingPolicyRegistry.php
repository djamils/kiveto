<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Service;

use App\System\Money\Domain\RoundingPolicy\AccountingRounding;
use App\System\Money\Domain\RoundingPolicy\CommercialRounding;
use App\System\Money\Domain\RoundingPolicy\PsychologicalRounding;
use App\System\Money\Domain\RoundingPolicy\RoundingPolicy;
use App\System\Money\Domain\RoundingPolicy\SwissCashRounding;
use App\System\Money\Domain\ValueObject\PsychologicalStrategy;
use App\System\Money\Domain\ValueObject\RoundingPolicyId;

final class RoundingPolicyRegistry
{
    public function get(RoundingPolicyId $id): RoundingPolicy
    {
        return match ($id) {
            RoundingPolicyId::ACCOUNTING    => $this->accounting(),
            RoundingPolicyId::COMMERCIAL    => $this->commercial(),
            RoundingPolicyId::SWISS_CASH    => $this->swissCash(),
            RoundingPolicyId::PSYCHOLOGICAL => $this->psychological(),
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

    public function psychological(PsychologicalStrategy $strategy = PsychologicalStrategy::NINETY_NINE): PsychologicalRounding
    {
        return new PsychologicalRounding($strategy);
    }
}
