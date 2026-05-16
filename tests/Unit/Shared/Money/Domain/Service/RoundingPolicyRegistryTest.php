<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Money\Domain\Service;

use App\Shared\Money\Domain\RoundingPolicy\AccountingRounding;
use App\Shared\Money\Domain\RoundingPolicy\CommercialRounding;
use App\Shared\Money\Domain\RoundingPolicy\SwissCashRounding;
use App\Shared\Money\Domain\Service\RoundingPolicyRegistry;
use App\Shared\Money\Domain\ValueObject\RoundingPolicyId;
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
}
