<?php

declare(strict_types=1);

namespace Tests\Unit\System\PharmaceuticalRegistry\Domain\ValueObject;

use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MarketingAuthorizationStatusTest extends TestCase
{
    #[DataProvider('provideIsMarketableReturnsTrueCases')]
    public function testIsMarketableReturnsTrue(MarketingAuthorizationStatus $status): void
    {
        self::assertTrue($status->isMarketable());
    }

    /** @return array<array{MarketingAuthorizationStatus}> */
    public static function provideIsMarketableReturnsTrueCases(): iterable
    {
        return [
            [MarketingAuthorizationStatus::ACTIVE],
            [MarketingAuthorizationStatus::EXCEPTIONAL_CIRCUMSTANCES],
            [MarketingAuthorizationStatus::UNLIMITED],
        ];
    }

    #[DataProvider('provideIsMarketableReturnsFalseCases')]
    public function testIsMarketableReturnsFalse(MarketingAuthorizationStatus $status): void
    {
        self::assertFalse($status->isMarketable());
    }

    /** @return array<array{MarketingAuthorizationStatus}> */
    public static function provideIsMarketableReturnsFalseCases(): iterable
    {
        return [
            [MarketingAuthorizationStatus::UNDER_REVIEW],
            [MarketingAuthorizationStatus::WITHDRAWN],
            [MarketingAuthorizationStatus::WITHDRAWN_WITH_DEROGATION],
            [MarketingAuthorizationStatus::SUSPENDED],
            [MarketingAuthorizationStatus::REFUSED],
            [MarketingAuthorizationStatus::ABANDONED],
            [MarketingAuthorizationStatus::LAPSED],
        ];
    }
}
