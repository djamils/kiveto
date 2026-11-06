<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Query\IsOnlineBookingOpen;

use App\Context\Scheduling\Application\Port\ClinicOpeningHours;
use App\Context\Scheduling\Application\Query\IsOnlineBookingOpen\IsOnlineBookingOpen;
use App\Context\Scheduling\Application\Query\IsOnlineBookingOpen\IsOnlineBookingOpenHandler;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Tests\Unit\Context\Scheduling\Support\FakeAvailabilitySources;
use App\Tests\Unit\Context\Scheduling\Support\FakeClinicFeatures;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryOnlineBookingSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IsOnlineBookingOpenHandlerTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000c001';

    /**
     * @return iterable<string, array{bool, ?bool, bool}>
     */
    public static function provideOpenOnlyWhenEnabledAndTheClinicIsActiveCases(): iterable
    {
        yield 'enabled, active' => [true, true, true];
        yield 'disabled, active' => [false, true, false];
        yield 'enabled, suspended' => [true, false, false];
        yield 'enabled, unknown clinic' => [true, null, false];
    }

    #[DataProvider('provideOpenOnlyWhenEnabledAndTheClinicIsActiveCases')]
    public function testOpenOnlyWhenEnabledAndTheClinicIsActive(bool $enabled, ?bool $active, bool $expected): void
    {
        $settings = new InMemoryOnlineBookingSettings();
        $settings->save(new OnlineBookingSettings(ClinicId::fromString(self::CLINIC), $enabled, 15, 120, 24, 60, 24, false));
        $sources = new FakeAvailabilitySources();
        if (null !== $active) {
            $sources->clinic = new ClinicOpeningHours($active, new \DateTimeZone('Europe/Paris'), [], []);
        }

        self::assertSame($expected, new IsOnlineBookingOpenHandler($settings, $sources)(new IsOnlineBookingOpen(self::CLINIC)));
    }

    /**
     * « Prise de RDV en ligne » switched off by Kiveto closes a clinic that
     * opened it; switched on, the clinic's own settings decide.
     */
    #[DataProvider('provideTheFeatureSwitchedOffClosesOnlineBookingCases')]
    public function testTheFeatureSwitchedOffClosesOnlineBooking(bool $featureOn, bool $enabled, bool $expected): void
    {
        $settings = new InMemoryOnlineBookingSettings();
        $settings->save(new OnlineBookingSettings(ClinicId::fromString(self::CLINIC), $enabled, 15, 120, 24, 60, 24, false));
        $sources         = new FakeAvailabilitySources();
        $sources->clinic = new ClinicOpeningHours(true, new \DateTimeZone('Europe/Paris'), [], []);
        $features        = new FakeClinicFeatures(onlineBooking: $featureOn);

        self::assertSame($expected, new IsOnlineBookingOpenHandler($settings, $sources, $features)(new IsOnlineBookingOpen(self::CLINIC)));
        self::assertSame([self::CLINIC], $features->asked);
    }

    /**
     * @return iterable<string, array{bool, bool, bool}>
     */
    public static function provideTheFeatureSwitchedOffClosesOnlineBookingCases(): iterable
    {
        yield 'feature off, settings enabled' => [false, true, false];

        yield 'feature on, settings enabled' => [true, true, true];

        yield 'feature on, settings disabled' => [true, false, false];
    }

    public function testAnUnknownClinicIsClosedWithoutAskingTheFeature(): void
    {
        $features = new FakeClinicFeatures();

        self::assertFalse(new IsOnlineBookingOpenHandler(new InMemoryOnlineBookingSettings(), new FakeAvailabilitySources(), $features)(new IsOnlineBookingOpen(self::CLINIC)));
        self::assertSame([], $features->asked);
    }
}
