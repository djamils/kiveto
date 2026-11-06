<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Infrastructure\Adapter\FeatureCatalog;

use App\Context\Scheduling\Infrastructure\Adapter\FeatureCatalog\QueryBusClinicFeatures;
use App\System\FeatureCatalog\Application\Query\IsFeatureEnabled\IsFeatureEnabled;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QueryBusClinicFeaturesTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000f8a1';

    #[DataProvider('provideOnlyATrueAnswerSwitchesTheFeatureOnCases')]
    public function testOnlyATrueAnswerSwitchesTheFeatureOn(mixed $answer, bool $on): void
    {
        $bus      = new AnsweringQueryBus()->answer(IsFeatureEnabled::class, $answer);
        $features = new QueryBusClinicFeatures($bus);

        self::assertSame([$on, $on], [$features->onlineBookingOn(self::CLINIC), $features->smsRemindersOn(self::CLINIC)]);
        self::assertSame(
            [[self::CLINIC, 'portal.online_booking'], [self::CLINIC, 'notification.sms_reminders']],
            array_map(static fn (object $query): array => $query instanceof IsFeatureEnabled ? [$query->clinicId, $query->code] : [], $bus->asked),
        );
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function provideOnlyATrueAnswerSwitchesTheFeatureOnCases(): iterable
    {
        yield 'on' => [true, true];

        yield 'off' => [false, false];

        yield 'no answer' => [null, false];

        yield 'truthy but not true' => [1, false];
    }
}
