<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Domain\Service;

use App\System\Money\Domain\RoundingPolicy\AccountingRounding;
use App\System\Money\Domain\RoundingPolicy\CommercialRounding;
use App\System\Money\Domain\RoundingPolicy\PsychologicalRounding;
use App\System\Money\Domain\RoundingPolicy\SwissCashRounding;
use App\System\Money\Domain\Service\RoundingPolicyRegistry;
use App\System\Money\Domain\ValueObject\RoundingPolicyId;
use PHPUnit\Framework\TestCase;

final class RoundingPolicyRegistryTest extends TestCase
{
    private RoundingPolicyRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new RoundingPolicyRegistry();
    }

    public function testGetAccounting(): void
    {
        $policy = $this->registry->get(RoundingPolicyId::ACCOUNTING);

        self::assertInstanceOf(AccountingRounding::class, $policy);
    }

    public function testGetCommercial(): void
    {
        $policy = $this->registry->get(RoundingPolicyId::COMMERCIAL);

        self::assertInstanceOf(CommercialRounding::class, $policy);
    }

    public function testGetSwissCash(): void
    {
        $policy = $this->registry->get(RoundingPolicyId::SWISS_CASH);

        self::assertInstanceOf(SwissCashRounding::class, $policy);
    }

    public function testGetPsychological(): void
    {
        $policy = $this->registry->get(RoundingPolicyId::PSYCHOLOGICAL);

        self::assertInstanceOf(PsychologicalRounding::class, $policy);
    }

    public function testAccountingReturnsAccountingRounding(): void
    {
        $policy = $this->registry->accounting();

        self::assertInstanceOf(AccountingRounding::class, $policy);
        self::assertSame(RoundingPolicyId::ACCOUNTING, $policy->id());
    }

    public function testCommercialReturnsCommercialRounding(): void
    {
        $policy = $this->registry->commercial();

        self::assertInstanceOf(CommercialRounding::class, $policy);
        self::assertSame(RoundingPolicyId::COMMERCIAL, $policy->id());
    }

    public function testSwissCashReturnsSwissCashRounding(): void
    {
        $policy = $this->registry->swissCash();

        self::assertInstanceOf(SwissCashRounding::class, $policy);
        self::assertSame(RoundingPolicyId::SWISS_CASH, $policy->id());
    }

    public function testPsychologicalReturnsDefaultNinetyNine(): void
    {
        $policy = $this->registry->psychological();

        self::assertInstanceOf(PsychologicalRounding::class, $policy);
        self::assertSame(RoundingPolicyId::PSYCHOLOGICAL, $policy->id());
    }
}
