<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Query\GetOnlineAvailability;

use App\Context\Scheduling\Application\Port\ClinicOpeningHours;
use App\Context\Scheduling\Application\Port\PlanningBlockOccurrence;
use App\Context\Scheduling\Application\Query\GetOnlineAvailability\GetOnlineAvailability;
use App\Context\Scheduling\Application\Query\GetOnlineAvailability\GetOnlineAvailabilityHandler;
use App\Context\Scheduling\Application\Query\GetOnlineAvailability\OnlineAvailabilityDay;
use App\Context\Scheduling\Application\Service\OnlineSlotChecker;
use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingNotOpenException;
use App\Context\Scheduling\Domain\Booking\BusyInterval;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\Booking\InvalidAvailabilityRangeException;
use App\Context\Scheduling\Domain\Booking\OnlineBookingNotFoundException;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Scheduling\Support\FakeAvailabilitySources;
use App\Tests\Unit\Context\Scheduling\Support\FakeClinicFeatures;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryBookingMotifs;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryOnlineBookingSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GetOnlineAvailabilityHandlerTest extends TestCase
{
    private const string CLINIC       = '01960000-0000-7000-8000-00000000c001';
    private const string OTHER_CLINIC = '01960000-0000-7000-8000-00000000c002';
    private const string CONSULT      = '01960000-0000-7000-8000-00000000b001';
    private const string MANUAL       = '01960000-0000-7000-8000-00000000b002';
    private const string VET          = '01960000-0000-7000-8000-00000000d001';
    private const string OTHER_VET    = '01960000-0000-7000-8000-00000000d002';
    private const string MOVED        = '01960000-0000-7000-8000-00000000a001';

    private InMemoryBookingMotifs $motifs;
    private InMemoryOnlineBookingSettings $settings;
    private FakeAvailabilitySources $sources;

    protected function setUp(): void
    {
        $this->motifs = new InMemoryBookingMotifs();
        $this->motifs->save(self::motif(self::CONSULT, ConfirmationMode::AUTO));
        $this->motifs->save(self::motif(self::MANUAL, ConfirmationMode::MANUAL));

        $this->settings = new InMemoryOnlineBookingSettings();
        $this->settings->save(self::settings(enabled: true));

        $this->sources           = new FakeAvailabilitySources();
        $this->sources->clinic   = self::clinic(active: true);
        $this->sources->eligible = [self::VET, self::OTHER_VET];

        // Mon 13: a consultation block, a surgery block (type not allowed by the motif)
        // Tue 14: the weekly block, a one-off leave inside it, a booking at 09:00
        // Wed 15: exceptional closure; Thu 16: open, no block
        $this->sources->occurrences = [
            new PlanningBlockOccurrence('2026-04-13', '09:00', '12:00', PlanningBlockType::CONSULTATION, 0),
            new PlanningBlockOccurrence('2026-04-13', '12:00', '12:30', PlanningBlockType::SURGERY, 0),
            new PlanningBlockOccurrence('2026-04-14', '09:00', '12:00', PlanningBlockType::CONSULTATION, 0),
            new PlanningBlockOccurrence('2026-04-14', '10:00', '11:00', PlanningBlockType::LEAVE, 0),
            new PlanningBlockOccurrence('2026-04-15', '09:00', '12:00', PlanningBlockType::CONSULTATION, 0),
        ];
        $this->sources->appointments = [
            BusyInterval::appointment(new \DateTimeImmutable('2026-04-14T09:00:00+02:00'), new \DateTimeImmutable('2026-04-14T09:30:00+02:00')),
        ];
    }

    public function testListsTheDaysWithTheirSlotsFromNowPlusTheLeadTime(): void
    {
        $availability = $this->handler()(new GetOnlineAvailability(self::CLINIC, self::CONSULT, self::VET, '2026-04-13', '2026-04-16'));

        self::assertSame(
            [
                // Now is Monday 08:00, lead time 2 h
                ['2026-04-13', true, ['2026-04-13T10:00:00+02:00', '2026-04-13T10:15:00+02:00', '2026-04-13T10:30:00+02:00', '2026-04-13T10:45:00+02:00', '2026-04-13T11:00:00+02:00', '2026-04-13T11:15:00+02:00', '2026-04-13T11:30:00+02:00']],
                // Booking 09:00–09:30, leave 10:00–11:00 (AC 150)
                ['2026-04-14', true, ['2026-04-14T09:30:00+02:00', '2026-04-14T11:00:00+02:00', '2026-04-14T11:15:00+02:00', '2026-04-14T11:30:00+02:00']],
                // Exceptional closure (AC 41)
                ['2026-04-15', false, []],
                // Open, but no block: no slot (HARD rule)
                ['2026-04-16', true, []],
            ],
            self::days($availability->days),
        );

        self::assertSame([['2026-04-13', '2026-04-16', false]], $this->sources->blockReads);
        // The appointments of the whole local range
        self::assertSame([['2026-04-13T00:00:00+02:00', '2026-04-17T00:00:00+02:00', null, false]], $this->sources->appointmentReads);
    }

    /**
     * @param list<string> $tuesday
     */
    #[DataProvider('provideAPendingBookingWaitsForTheManualLeadTimeCases')]
    public function testAPendingBookingWaitsForTheManualLeadTime(string $motif, bool $newClient, array $tuesday): void
    {
        $availability = $this->handler()(new GetOnlineAvailability(self::CLINIC, $motif, self::VET, '2026-04-13', '2026-04-14', $newClient));

        self::assertSame([['2026-04-13', true, []], ['2026-04-14', true, $tuesday]], self::days($availability->days));
    }

    /**
     * @return iterable<string, array{string, bool, list<string>}>
     */
    public static function provideAPendingBookingWaitsForTheManualLeadTimeCases(): iterable
    {
        // AC 147: manual lead time 26 h → from Tuesday 10:00
        $fromTen = ['2026-04-14T11:00:00+02:00', '2026-04-14T11:15:00+02:00', '2026-04-14T11:30:00+02:00'];

        yield 'manual motif, known client' => [self::MANUAL, false, $fromTen];
        yield 'auto motif, new client' => [self::CONSULT, true, $fromTen];
    }

    public function testDaysBeyondTheHorizonAreNotOpen(): void
    {
        $this->settings->save(self::settings(enabled: true, horizonDays: 1));

        $availability = $this->handler()(new GetOnlineAvailability(self::CLINIC, self::CONSULT, self::VET, '2026-04-14', '2026-04-16'));

        self::assertSame([['2026-04-14', true], ['2026-04-15', false], ['2026-04-16', false]], array_map(
            static fn (OnlineAvailabilityDay $day): array => [$day->date, $day->open],
            $availability->days,
        ));
    }

    public function testAnEmptyOpeningWeekOpensNoDay(): void
    {
        $this->sources->clinic = new ClinicOpeningHours(true, new \DateTimeZone('Europe/Paris'), [], []);

        $availability = $this->handler()(new GetOnlineAvailability(self::CLINIC, self::CONSULT, self::VET, '2026-04-13', '2026-04-14'));

        self::assertSame([['2026-04-13', false, []], ['2026-04-14', false, []]], self::days($availability->days));
    }

    /**
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('provideRefusalsCases')]
    public function testRefusals(string $arrange, GetOnlineAvailability $query, string $exception, string $detail): void
    {
        match ($arrange) {
            'disable'    => $this->settings->save(self::settings(enabled: false)),
            'suspend'    => $this->sources->clinic = self::clinic(active: false),
            'forget'     => $this->sources->clinic = null,
            'archive'    => $this->motifs->motifs[self::CONSULT]->archive(new \DateTimeImmutable('2026-04-01')),
            'offline'    => $this->motifs->save(self::motif(self::CONSULT, ConfirmationMode::AUTO, online: false)),
            'other'      => $this->settings->save(self::settings(enabled: true, clinicId: self::OTHER_CLINIC)),
            'ineligible' => $this->sources->eligible = [self::OTHER_VET],
            'no-new'     => $this->settings->save(self::settings(enabled: true, newClients: false)),
            'known-only' => $this->motifs->save(self::motif(self::CONSULT, ConfirmationMode::AUTO, newClients: false)),
            default      => null,
        };

        try {
            $this->handler()($query);
            self::fail('Expected ' . $exception);
        } catch (\Throwable $e) {
            self::assertInstanceOf($exception, $e);
            self::assertSame($detail, match (true) {
                $e instanceof OnlineBookingNotFoundException    => $e->subject,
                $e instanceof InvalidAvailabilityRangeException => $e->field . ': ' . $e->getMessage(),
                default                                         => $e->getMessage(),
            });
        }
    }

    /**
     * @return iterable<string, array{string, GetOnlineAvailability, class-string<\Throwable>, string}>
     */
    public static function provideRefusalsCases(): iterable
    {
        $query   = static fn (string $motif = self::CONSULT, string $vet = self::VET, string $from = '2026-04-13', string $to = '2026-04-14', string $clinic = self::CLINIC): GetOnlineAvailability => new GetOnlineAvailability($clinic, $motif, $vet, $from, $to);
        $notOpen = 'La réservation en ligne n\'est pas ouverte pour cette clinique.';

        // AC 43 / AC 156: booking_not_open
        yield 'settings disabled' => ['disable', $query(), BookingNotOpenException::class, $notOpen];
        yield 'clinic suspended' => ['suspend', $query(), BookingNotOpenException::class, $notOpen];
        yield 'clinic unknown' => ['forget', $query(), BookingNotOpenException::class, $notOpen];

        // AC 43: not_found
        yield 'motif unknown' => ['', $query('01960000-0000-7000-8000-00000000b009'), OnlineBookingNotFoundException::class, 'motif'];
        yield 'motif malformed' => ['', $query('vaccin'), OnlineBookingNotFoundException::class, 'motif'];
        yield 'motif archived' => ['archive', $query(), OnlineBookingNotFoundException::class, 'motif'];
        yield 'motif not online' => ['offline', $query(), OnlineBookingNotFoundException::class, 'motif'];
        yield 'motif of another clinic' => ['other', $query(clinic: self::OTHER_CLINIC), OnlineBookingNotFoundException::class, 'motif'];
        yield 'practitioner not on the motif' => ['', $query(vet: self::OTHER_VET), OnlineBookingNotFoundException::class, 'practitioner'];
        yield 'practitioner malformed' => ['', $query(vet: 'dr-house'), OnlineBookingNotFoundException::class, 'practitioner'];
        yield 'practitioner no longer eligible' => ['ineligible', $query(), OnlineBookingNotFoundException::class, 'practitioner'];

        // The public directory (S7c): a new client needs both the settings and the motif open to new clients
        $newClient = new GetOnlineAvailability(self::CLINIC, self::CONSULT, self::VET, '2026-04-13', '2026-04-14', true);
        yield 'new clients not taken' => ['no-new', $newClient, BookingNotOpenException::class, $notOpen];
        yield 'motif not open to new clients' => ['known-only', $newClient, OnlineBookingNotFoundException::class, 'motif'];

        // AC 43: validation_failed
        yield 'from malformed' => ['', $query(from: '13/04/2026'), InvalidAvailabilityRangeException::class, 'from: La date doit être au format AAAA-MM-JJ.'];
        yield 'to not a date' => ['', $query(to: '2026-04-31'), InvalidAvailabilityRangeException::class, 'to: La date doit être au format AAAA-MM-JJ.'];
        yield 'from after to' => ['', $query(from: '2026-04-14', to: '2026-04-13'), InvalidAvailabilityRangeException::class, 'to: La date de fin doit être postérieure ou égale à la date de début.'];
        yield 'fifteen days' => ['', $query(from: '2026-04-13', to: '2026-04-27'), InvalidAvailabilityRangeException::class, 'to: La période demandée ne peut pas dépasser 14 jours.'];
        yield 'from before the local today' => ['', $query(from: '2026-04-12'), InvalidAvailabilityRangeException::class, 'from: La date de début ne peut pas être dans le passé.'];
    }

    public function testFourteenDaysFromTheLocalTodayAreAccepted(): void
    {
        $availability = $this->handler()(new GetOnlineAvailability(self::CLINIC, self::CONSULT, self::VET, '2026-04-13', '2026-04-26'));

        self::assertCount(14, $availability->days);
    }

    public function testASingleStartIsCheckedWithCurrentReads(): void
    {
        $checker  = $this->checker();
        $clinicId = ClinicId::fromString(self::CLINIC);
        $motifId  = BookingMotifId::fromString(self::CONSULT);
        $vet      = UserId::fromString(self::VET);
        $moved    = AppointmentId::fromString(self::MOVED);

        self::assertTrue($checker->isBookable($clinicId, $motifId, $vet, new \DateTimeImmutable('2026-04-14T09:00:00Z'), false, $moved));
        self::assertFalse($checker->isBookable($clinicId, $motifId, $vet, new \DateTimeImmutable('2026-04-14T10:15:00+02:00'), false), 'during the leave');
        self::assertFalse($checker->isBookable($clinicId, $motifId, $vet, new \DateTimeImmutable('2026-04-14T11:05:00+02:00'), false), 'off the grid');
        self::assertFalse($checker->isBookable($clinicId, $motifId, $vet, new \DateTimeImmutable('2026-04-14T09:30:00+02:00'), true), 'before the manual lead time');
        self::assertTrue($checker->isBookable($clinicId, $motifId, $vet, new \DateTimeImmutable('2026-04-14T09:30:00+02:00'), false));

        // The start's local day, FOR SHARE, the moved booking left out
        self::assertSame(['2026-04-14', '2026-04-14', true], $this->sources->blockReads[0]);
        self::assertSame(['2026-04-14T00:00:00+02:00', '2026-04-15T00:00:00+02:00', self::MOVED, true], $this->sources->appointmentReads[0]);
    }

    public function testASingleStartIsRefusedLikeTheAvailability(): void
    {
        $this->settings->save(self::settings(enabled: false));

        $this->expectException(BookingNotOpenException::class);

        $this->checker()->isBookable(ClinicId::fromString(self::CLINIC), BookingMotifId::fromString(self::CONSULT), UserId::fromString(self::VET), new \DateTimeImmutable('2026-04-14T09:00:00Z'), false);
    }

    /**
     * Kiveto switched « Prise de RDV en ligne » off for the clinic: nothing
     * is offered online, whatever its own settings say.
     */
    public function testTheFeatureSwitchedOffClosesEveryOnlineBooking(): void
    {
        $features = new FakeClinicFeatures(onlineBooking: false);
        $checker  = $this->checker($features);

        try {
            $checker->isBookable(ClinicId::fromString(self::CLINIC), BookingMotifId::fromString(self::CONSULT), UserId::fromString(self::VET), new \DateTimeImmutable('2026-04-14T09:00:00Z'), false);
            self::fail('A clinic without the feature was expected to take no online booking.');
        } catch (BookingNotOpenException $e) {
            self::assertSame('La réservation en ligne n\'est pas ouverte pour cette clinique.', $e->getMessage());
        }

        $this->expectException(BookingNotOpenException::class);
        new GetOnlineAvailabilityHandler($checker)(new GetOnlineAvailability(self::CLINIC, self::CONSULT, self::VET, '2026-04-13', '2026-04-14'));
    }

    public function testTheFeatureSwitchedOnLeavesTheClinicsSettingsToDecide(): void
    {
        $features = new FakeClinicFeatures(onlineBooking: true);

        self::assertTrue($this->checker($features)->isBookable(ClinicId::fromString(self::CLINIC), BookingMotifId::fromString(self::CONSULT), UserId::fromString(self::VET), new \DateTimeImmutable('2026-04-14T09:00:00Z'), false));
        self::assertSame([self::CLINIC], $features->asked);
    }

    private function handler(): GetOnlineAvailabilityHandler
    {
        return new GetOnlineAvailabilityHandler($this->checker());
    }

    private function checker(?FakeClinicFeatures $features = null): OnlineSlotChecker
    {
        return new OnlineSlotChecker(
            $this->motifs,
            $this->settings,
            $this->sources,
            $this->sources,
            $this->sources,
            $this->sources,
            // Monday 13 April 2026, 08:00 in Paris
            new FrozenClock(new \DateTimeImmutable('2026-04-13T06:00:00Z')),
            $features,
        );
    }

    /**
     * @param list<OnlineAvailabilityDay> $days
     *
     * @return list<array{string, bool, list<string>}>
     */
    private static function days(array $days): array
    {
        return array_map(
            static fn (OnlineAvailabilityDay $day): array => [$day->date, $day->open, array_map(static fn (\DateTimeImmutable $slot): string => $slot->format('c'), $day->slots)],
            $days,
        );
    }

    private static function motif(string $id, ConfirmationMode $confirmation, bool $online = true, bool $newClients = true): BookingMotif
    {
        return BookingMotif::create(
            BookingMotifId::fromString($id),
            ClinicId::fromString(self::CLINIC),
            'Consultation',
            null,
            30,
            $online,
            $confirmation,
            [PlanningBlockType::CONSULTATION],
            [UserId::fromString(self::VET)],
            null,
            $newClients,
            0,
        );
    }

    private static function settings(bool $enabled, int $horizonDays = 60, string $clinicId = self::CLINIC, bool $newClients = true): OnlineBookingSettings
    {
        return new OnlineBookingSettings(
            clinicId: ClinicId::fromString($clinicId),
            enabled: $enabled,
            gridStepMinutes: 15,
            minLeadTimeMinutes: 120,
            minLeadTimeManualHours: 26,
            horizonDays: $horizonDays,
            cancellationWindowHours: 24,
            newClientBookingEnabled: $newClients,
        );
    }

    private static function clinic(bool $active): ClinicOpeningHours
    {
        $weekday = [['opensAt' => '08:30', 'closesAt' => '12:30']];

        return new ClinicOpeningHours(
            active: $active,
            timeZone: new \DateTimeZone('Europe/Paris'),
            week: [1 => $weekday, 2 => $weekday, 3 => $weekday, 4 => $weekday, 5 => $weekday],
            closedDates: ['2026-04-15'],
        );
    }
}
