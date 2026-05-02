<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Regulatory\Jurisdiction\France;

use App\Context\Regulatory\Domain\Policy\RegulatoryPolicyInterface;
use App\Context\Regulatory\Jurisdiction\France\FranceRegulatoryPolicy;
use App\Context\Regulatory\Jurisdiction\France\FrenchWorkingDayCalculator;
use App\Tests\Unit\Context\Regulatory\Policy\JurisdictionPolicyContractCase;

final class FrancePolicyTest extends JurisdictionPolicyContractCase
{
    protected function createPolicy(): RegulatoryPolicyInterface
    {
        return new FranceRegulatoryPolicy(new FrenchWorkingDayCalculator());
    }
}
