<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Query\GetOnlineBookingSettings;

use App\Context\Scheduling\Application\Query\GetOnlineBookingSettings\GetOnlineBookingSettings;
use App\Context\Scheduling\Application\Query\GetOnlineBookingSettings\GetOnlineBookingSettingsHandler;
use App\Context\Scheduling\Application\Query\GetOnlineBookingSettings\OnlineBookingSettingsView;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryOnlineBookingSettings;
use PHPUnit\Framework\TestCase;

final class GetOnlineBookingSettingsHandlerTest extends TestCase
{
    public function testAClinicWithoutSettingsGetsTheDefaults(): void
    {
        $view = new GetOnlineBookingSettingsHandler(new InMemoryOnlineBookingSettings())(
            new GetOnlineBookingSettings('01960000-0000-7000-8000-00000000c001'),
        );

        self::assertSame(get_object_vars(new OnlineBookingSettingsView(
            enabled: false,
            gridStepMinutes: 15,
            minLeadTimeMinutes: 120,
            minLeadTimeManualHours: 24,
            horizonDays: 60,
            cancellationWindowHours: 24,
            newClientBookingEnabled: false,
        )), get_object_vars($view));
    }
}
