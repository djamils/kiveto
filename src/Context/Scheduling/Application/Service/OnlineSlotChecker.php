<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Service;

use App\Context\Scheduling\Application\Port\ClinicOpeningHours;
use App\Context\Scheduling\Application\Port\ClinicOpeningHoursInterface;
use App\Context\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Context\Scheduling\Application\Port\OccupyingAppointmentsReaderInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockOccurrencesReaderInterface;
use App\Context\Scheduling\Domain\Booking\AvailabilityCalculator;
use App\Context\Scheduling\Domain\Booking\BookableInterval;
use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingMotifRepositoryInterface;
use App\Context\Scheduling\Domain\Booking\BookingNotOpenException;
use App\Context\Scheduling\Domain\Booking\BusyInterval;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\Booking\DaySlots;
use App\Context\Scheduling\Domain\Booking\InvalidAvailabilityRangeException;
use App\Context\Scheduling\Domain\Booking\LocalTime;
use App\Context\Scheduling\Domain\Booking\OnlineBookingNotFoundException;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\Booking\OpeningWindow;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;

/**
 * Online availability (S4c, HARD rule): the days of a range for the Portal,
 * and the check of one start for a booking (Task 26), both through
 * `AvailabilityCalculator` on the same inputs.
 *
 * Refusals, in this order: `BookingNotOpenException` (settings disabled,
 * clinic not active or unknown), `OnlineBookingNotFoundException` (motif
 * unknown, archived or not online; practitioner not listed on the motif or no
 * longer eligible), `InvalidAvailabilityRangeException` (range only).
 */
final readonly class OnlineSlotChecker
{
    /** `to − from ≤ 13` days. */
    public const int MAX_RANGE_DAYS = 14;

    private const array PRACTITIONER_ROLES = ['VETERINARY', 'VETERINARY_ASSISTANT'];

    public function __construct(
        private BookingMotifRepositoryInterface $motifs,
        private OnlineBookingSettingsRepositoryInterface $settings,
        private ClinicOpeningHoursInterface $clinics,
        private MembershipEligibilityCheckerInterface $eligibility,
        private PlanningBlockOccurrencesReaderInterface $blocks,
        private OccupyingAppointmentsReaderInterface $appointments,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The clinic-local days [fromDate, toDate] (`YYYY-MM-DD`, inclusive).
     *
     * @throws BookingNotOpenException
     * @throws OnlineBookingNotFoundException
     * @throws InvalidAvailabilityRangeException
     *
     * @return list<DaySlots>
     */
    public function availability(
        ClinicId $clinicId,
        BookingMotifId $motifId,
        UserId $practitionerId,
        string $fromDate,
        string $toDate,
        bool $newClient,
    ): array {
        $now                        = $this->clock->now();
        [$settings, $motif, $hours] = $this->target($clinicId, $motifId, $practitionerId, $now);

        self::assertRange($fromDate, $toDate, $now->setTimezone($hours->timeZone)->format('Y-m-d'));

        return new AvailabilityCalculator()->days(
            $fromDate,
            $toDate,
            ...$this->inputs($clinicId, $practitionerId, $settings, $motif, $hours, $now, $newClient, $fromDate, $toDate, null, false),
        );
    }

    /**
     * Whether that exact start is offered online: on the grid, free, within
     * the blocks, the opening hours, the lead time and the horizon. Reads
     * `FOR SHARE`: call it under the practitioner-day lock; a moved
     * appointment is left out with `excludeAppointmentId`.
     *
     * @throws BookingNotOpenException
     * @throws OnlineBookingNotFoundException
     */
    public function isBookable(
        ClinicId $clinicId,
        BookingMotifId $motifId,
        UserId $practitionerId,
        \DateTimeImmutable $start,
        bool $newClient,
        ?AppointmentId $excludeAppointmentId = null,
    ): bool {
        $now                        = $this->clock->now();
        [$settings, $motif, $hours] = $this->target($clinicId, $motifId, $practitionerId, $now);
        $date                       = $start->setTimezone($hours->timeZone)->format('Y-m-d');

        return new AvailabilityCalculator()->isBookable(
            $start,
            ...$this->inputs($clinicId, $practitionerId, $settings, $motif, $hours, $now, $newClient, $date, $date, $excludeAppointmentId, true),
        );
    }

    /**
     * The motif, when the clinic is open online and the practitioner is
     * bookable for it: a booking reads its duration and confirmation mode
     * before taking the lock.
     *
     * @throws BookingNotOpenException
     * @throws OnlineBookingNotFoundException
     */
    public function bookableMotif(ClinicId $clinicId, BookingMotifId $motifId, UserId $practitionerId): BookingMotif
    {
        return $this->target($clinicId, $motifId, $practitionerId, $this->clock->now())[1];
    }

    /**
     * @return array{OnlineBookingSettings, BookingMotif, ClinicOpeningHours}
     */
    private function target(ClinicId $clinicId, BookingMotifId $motifId, UserId $practitionerId, \DateTimeImmutable $now): array
    {
        $settings = $this->settings->forClinic($clinicId);
        $hours    = $this->clinics->of($clinicId);

        if (null === $hours || !$settings->isOpenForBooking($hours->active)) {
            throw new BookingNotOpenException($clinicId->toString());
        }

        $motif = $this->motifs->findInClinic($motifId, $clinicId);

        if (null === $motif || $motif->isArchived() || !$motif->isOnlineEnabled()) {
            throw OnlineBookingNotFoundException::motif($motifId->toString());
        }

        $bookable = $motif->isBookableWith($practitionerId)
            && $this->eligibility->isUserEligibleForClinicAt($practitionerId, $clinicId, $now, self::PRACTITIONER_ROLES);

        if (!$bookable) {
            throw OnlineBookingNotFoundException::practitioner($practitionerId->toString());
        }

        return [$settings, $motif, $hours];
    }

    /**
     * The calculator's arguments after the range: horizon, time zone,
     * duration, grid, earliest start and the intervals of [fromDate, toDate].
     *
     * @return array{string, \DateTimeZone, int, int, \DateTimeImmutable, list<BookableInterval>, list<OpeningWindow>, list<BusyInterval>}
     */
    private function inputs(
        ClinicId $clinicId,
        UserId $practitionerId,
        OnlineBookingSettings $settings,
        BookingMotif $motif,
        ClinicOpeningHours $hours,
        \DateTimeImmutable $now,
        bool $newClient,
        string $fromDate,
        string $toDate,
        ?AppointmentId $excludeAppointmentId,
        bool $currentRead,
    ): array {
        $tz = $hours->timeZone;

        // A booking that would wait for the clinic needs the manual lead time (AC 147)
        $pending  = $newClient || ConfirmationMode::MANUAL === $motif->confirmationKnownClient();
        $leadTime = $pending ? $settings->pendingMinLeadTimeMinutes() : $settings->minLeadTimeMinutes();
        $earliest = $now->setTimestamp($now->getTimestamp() + $leadTime * 60);
        $lastDate = $now->setTimezone($tz)->modify(\sprintf('+%d days', $settings->horizonDays()))->format('Y-m-d');
        $bookable = [];
        $busy     = [];
        $opening  = [];

        foreach ($this->blocks->occurrencesBetween($clinicId, $practitionerId, $fromDate, $toDate, $currentRead) as $occurrence) {
            $startMinute = LocalTime::minuteOf($occurrence->startTime);
            $endMinute   = LocalTime::minuteOf($occurrence->endTime);

            if (!$occurrence->type->acceptsAppointments()) {
                // Leave, training, admin, break: busy whatever their anchor date (S4b-F1)
                $busy[] = BusyInterval::unavailable(
                    LocalTime::instant($occurrence->date, $startMinute, $tz),
                    LocalTime::instant($occurrence->date, $endMinute, $tz),
                );
            } elseif ($motif->allowsBlockType($occurrence->type)) {
                $bookable[] = new BookableInterval($occurrence->date, $startMinute, $endMinute, $occurrence->capacityPerHour);
            }
        }

        $dayAfter = new \DateTimeImmutable($toDate)->modify('+1 day')->format('Y-m-d');
        $busy     = [
            ...$busy,
            ...$this->appointments->occupyingBetween(
                $clinicId,
                $practitionerId,
                LocalTime::instant($fromDate, 0, $tz),
                LocalTime::instant($dayAfter, 0, $tz),
                $excludeAppointmentId,
                $currentRead,
            ),
        ];

        for ($date = $fromDate; $date <= $toDate; $date = new \DateTimeImmutable($date)->modify('+1 day')->format('Y-m-d')) {
            foreach ($hours->slotsOn($date) as $slot) {
                $opening[] = new OpeningWindow($date, LocalTime::minuteOf($slot['opensAt']), LocalTime::minuteOf($slot['closesAt']));
            }
        }

        return [$lastDate, $tz, $motif->durationMin(), $settings->gridStepMinutes(), $earliest, $bookable, $opening, $busy];
    }

    private static function assertRange(string $fromDate, string $toDate, string $today): void
    {
        $from = self::date($fromDate, 'from');
        $to   = self::date($toDate, 'to');

        if ($from > $to) {
            throw InvalidAvailabilityRangeException::reversed();
        }

        if ($from->diff($to)->days >= self::MAX_RANGE_DAYS) {
            throw InvalidAvailabilityRangeException::tooLong(self::MAX_RANGE_DAYS);
        }

        if ($fromDate < $today) {
            throw InvalidAvailabilityRangeException::inThePast();
        }
    }

    private static function date(string $value, string $field): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));

        // Rejects 2026-02-30, which PHP would roll over to March
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw InvalidAvailabilityRangeException::malformed($field);
        }

        return $date;
    }
}
