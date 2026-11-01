<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Query\ListClinicsOpenToNewClients;

use App\Context\Scheduling\Application\Query\ListClinicsOpenToNewClients\ListClinicsOpenToNewClients;
use App\Context\Scheduling\Application\Query\ListClinicsOpenToNewClients\ListClinicsOpenToNewClientsHandler;
use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryBookingMotifs;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryOnlineBookingSettings;
use PHPUnit\Framework\TestCase;

/**
 * AC 82 (Scheduling side): a clinic takes new clients online only with its
 * settings enabled, new-client booking on, and a live online motif open to
 * new clients.
 */
final class ListClinicsOpenToNewClientsHandlerTest extends TestCase
{
    private const string OPEN        = '01960000-0000-7000-8000-00000000d001';
    private const string DISABLED    = '01960000-0000-7000-8000-00000000d002';
    private const string NO_NEW      = '01960000-0000-7000-8000-00000000d003';
    private const string NO_MOTIF    = '01960000-0000-7000-8000-00000000d004';
    private const string OFFLINE     = '01960000-0000-7000-8000-00000000d005';
    private const string ARCHIVED    = '01960000-0000-7000-8000-00000000d006';
    private const string NEVER_SAVED = '01960000-0000-7000-8000-00000000d007';
    private const string VET         = '01960000-0000-7000-8000-00000000d0ff';

    public function testOnlyTheClinicsMeetingEveryConditionAreAnswered(): void
    {
        $settings = new InMemoryOnlineBookingSettings();
        $motifs   = new InMemoryBookingMotifs();

        $settings->save(self::settings(self::OPEN, true, true));
        $settings->save(self::settings(self::DISABLED, false, true));
        $settings->save(self::settings(self::NO_NEW, true, false));
        $settings->save(self::settings(self::NO_MOTIF, true, true));
        $settings->save(self::settings(self::OFFLINE, true, true));
        $settings->save(self::settings(self::ARCHIVED, true, true));

        // Open: a motif for known clients only, then one open to new clients
        $motifs->save(self::motif('01960000-0000-7000-8000-00000000e001', self::OPEN, online: true, newClients: false));
        $motifs->save(self::motif('01960000-0000-7000-8000-00000000e002', self::OPEN, online: true, newClients: true));
        $motifs->save(self::motif('01960000-0000-7000-8000-00000000e003', self::DISABLED, online: true, newClients: true));
        $motifs->save(self::motif('01960000-0000-7000-8000-00000000e004', self::NO_NEW, online: true, newClients: true));
        $motifs->save(self::motif('01960000-0000-7000-8000-00000000e005', self::NO_MOTIF, online: true, newClients: false));
        $motifs->save(self::motif('01960000-0000-7000-8000-00000000e006', self::OFFLINE, online: false, newClients: true));
        $archived = self::motif('01960000-0000-7000-8000-00000000e007', self::ARCHIVED, online: true, newClients: true);
        $archived->archive(new \DateTimeImmutable('2026-10-01'));
        $motifs->save($archived);

        $handler = new ListClinicsOpenToNewClientsHandler($settings, $motifs);

        self::assertSame([self::OPEN], $handler(new ListClinicsOpenToNewClients([
            self::DISABLED, self::OPEN, self::NO_NEW, self::NO_MOTIF, self::OFFLINE, self::ARCHIVED, self::NEVER_SAVED, self::OPEN,
        ])));
        self::assertSame([], $handler(new ListClinicsOpenToNewClients([])));
    }

    private static function settings(string $clinicId, bool $enabled, bool $newClients): OnlineBookingSettings
    {
        return new OnlineBookingSettings(ClinicId::fromString($clinicId), $enabled, 15, 120, 24, 60, 24, $newClients);
    }

    private static function motif(string $id, string $clinicId, bool $online, bool $newClients): BookingMotif
    {
        return BookingMotif::create(
            BookingMotifId::fromString($id),
            ClinicId::fromString($clinicId),
            'Consultation',
            null,
            30,
            $online,
            ConfirmationMode::AUTO,
            [PlanningBlockType::CONSULTATION],
            [UserId::fromString(self::VET)],
            null,
            $newClients,
            0,
        );
    }
}
