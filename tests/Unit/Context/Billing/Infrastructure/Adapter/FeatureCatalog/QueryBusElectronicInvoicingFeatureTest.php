<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Adapter\FeatureCatalog;

use App\Context\Billing\Infrastructure\Adapter\FeatureCatalog\QueryBusElectronicInvoicingFeature;
use App\System\FeatureCatalog\Application\Query\IsFeatureEnabled\IsFeatureEnabled;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QueryBusElectronicInvoicingFeatureTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000f9a1';

    #[DataProvider('provideOnlyATrueAnswerSwitchesElectronicInvoicingOnCases')]
    public function testOnlyATrueAnswerSwitchesElectronicInvoicingOn(mixed $answer, bool $on): void
    {
        $bus = new AnsweringQueryBus()->answer(IsFeatureEnabled::class, $answer);

        self::assertSame($on, new QueryBusElectronicInvoicingFeature($bus)->isOnFor(self::CLINIC));

        $query = $bus->asked[0] ?? null;
        self::assertInstanceOf(IsFeatureEnabled::class, $query);
        self::assertSame([self::CLINIC, 'billing.electronic_invoicing'], [$query->clinicId, $query->code]);
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function provideOnlyATrueAnswerSwitchesElectronicInvoicingOnCases(): iterable
    {
        yield 'on' => [true, true];

        yield 'off' => [false, false];

        yield 'no answer' => [null, false];

        yield 'truthy but not true' => ['1', false];
    }
}
