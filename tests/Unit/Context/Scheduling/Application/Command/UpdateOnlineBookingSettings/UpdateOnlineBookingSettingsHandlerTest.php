<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\UpdateOnlineBookingSettings;

use App\Context\Scheduling\Application\Command\UpdateOnlineBookingSettings\UpdateOnlineBookingSettings;
use App\Context\Scheduling\Application\Command\UpdateOnlineBookingSettings\UpdateOnlineBookingSettingsHandler;
use App\Context\Scheduling\Application\Query\GetOnlineBookingSettings\GetOnlineBookingSettings;
use App\Context\Scheduling\Application\Query\GetOnlineBookingSettings\GetOnlineBookingSettingsHandler;
use App\Context\Scheduling\Application\Query\GetOnlineBookingSettings\OnlineBookingSettingsView;
use App\Context\Scheduling\Domain\Booking\InvalidBookingConfigurationException;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryOnlineBookingSettings;
use PHPUnit\Framework\TestCase;

final class UpdateOnlineBookingSettingsHandlerTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000c001';

    public function testSavesTheSettingsReadBackByTheQuery(): void
    {
        $settings = new InMemoryOnlineBookingSettings();

        new UpdateOnlineBookingSettingsHandler($settings)($this->command(minLeadTimeManualHours: 48));

        self::assertSame(
            get_object_vars(new OnlineBookingSettingsView(true, 10, 60, 48, 90, 12, true)),
            get_object_vars(new GetOnlineBookingSettingsHandler($settings)(new GetOnlineBookingSettings(self::CLINIC))),
        );
    }

    public function testOutOfBoundsValuesAreRefusedAndNothingIsSaved(): void
    {
        $settings = new InMemoryOnlineBookingSettings();

        try {
            new UpdateOnlineBookingSettingsHandler($settings)($this->command(minLeadTimeManualHours: 2));
            self::fail('The settings should be refused.');
        } catch (InvalidBookingConfigurationException $e) {
            self::assertSame('minLeadTimeManualHours', $e->field);
            self::assertSame('Le délai minimum d\'une demande à confirmer doit être compris entre 3 et 168 heures.', $e->getMessage());
        }

        self::assertSame([], $settings->settings);
    }

    private function command(int $minLeadTimeManualHours): UpdateOnlineBookingSettings
    {
        return new UpdateOnlineBookingSettings(
            clinicId: self::CLINIC,
            enabled: true,
            gridStepMinutes: 10,
            minLeadTimeMinutes: 60,
            minLeadTimeManualHours: $minLeadTimeManualHours,
            horizonDays: 90,
            cancellationWindowHours: 12,
            newClientBookingEnabled: true,
        );
    }
}
