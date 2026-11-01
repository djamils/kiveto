<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\Animal\Domain\ValueObject\PreventiveCareKind;
use App\Context\Scheduling\Domain\Booking\BookingPreventiveCareKind;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Slice 4: motifs, practitioners and availability of online booking
 * (AC 43, 51). Today is Monday 2 November 2026, 11:00 in Paris.
 */
final class BookingAvailabilityTest extends BookingApiTestCase
{
    private const string SUSPENDED_CLINIC = '01930000-0000-7000-8000-00000000c403';

    private string $vet;

    private string $vaccination;

    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.91';
        parent::setUp();

        $this->vet         = $this->veterinarian('Claire', 'Rousseau');
        $this->vaccination = $this->motif('Rappel vaccin', [$this->vet], BookingPreventiveCareKind::VACCINATION);
        $this->enableOnlineBooking();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEveryBookingRouteIsHiddenFromAClinicWithoutConfirmedLinkCases(): iterable
    {
        yield 'motifs' => ['/booking/motifs'];
        yield 'practitioners' => ['/booking/practitioners?motifId=01930000-0000-7000-8000-0000000000aa'];
        yield 'availability' => ['/booking/availability?motifId=01930000-0000-7000-8000-0000000000aa&practitionerId=01930000-0000-7000-8000-0000000000bb&from=2026-11-02&to=2026-11-03'];
    }

    #[DataProvider('provideEveryBookingRouteIsHiddenFromAClinicWithoutConfirmedLinkCases')]
    public function testEveryBookingRouteIsHiddenFromAClinicWithoutConfirmedLink(string $path): void
    {
        $this->enableOnlineBooking(clinicId: self::OTHER_CLINIC);

        self::assertSame('not_found', self::problemType($this->authGet(self::clinicPath($path, self::OTHER_CLINIC)), 404));
    }

    public function testTheMotifsOpenOnlineAreListedInTheClinicOrder(): void
    {
        $consultation = $this->motif('Consultation', [$this->vet], sortOrder: -1);
        $this->motif('Chirurgie', [$this->vet], online: false);
        $this->motif('Ancien motif', [$this->vet], archived: true);
        $this->motif('Ailleurs', [$this->vet], clinicId: self::OTHER_CLINIC);

        self::assertSame(
            [
                ['id' => $consultation, 'label' => 'Consultation', 'description' => 'Description de Consultation', 'durationMin' => 30, 'suggested' => false, 'suggestionReason' => null],
                ['id' => $this->vaccination, 'label' => 'Rappel vaccin', 'description' => 'Description de Rappel vaccin', 'durationMin' => 30, 'suggested' => false, 'suggestionReason' => null],
            ],
            self::list($this->authGet(self::clinicPath('/booking/motifs'))),
        );
    }

    public function testAMotifOfTheKindOfAnOverdueOrDueCareIsSuggestedForThePet(): void
    {
        $rex  = $this->pet('Rex');
        $nala = $this->pet('Nala');
        $milo = $this->pet('Milo');
        $this->careAct($rex, PreventiveCareKind::VACCINATION, 'CHPPiL', '2025-10-20', '2026-10-20');
        $this->careAct($nala, PreventiveCareKind::VACCINATION, 'Typhus', '2025-11-22', '2026-11-22');
        $this->careAct($milo, PreventiveCareKind::VACCINATION, 'Rage', '2026-06-01', '2027-06-01');

        self::assertSame([[true, 'Rappel de vaccin en retard']], self::suggestions($this->authGet(self::clinicPath('/booking/motifs?petId=' . $rex))));
        self::assertSame([[true, 'Rappel de vaccin à prévoir']], self::suggestions($this->authGet(self::clinicPath('/booking/motifs?petId=' . $nala))));
        self::assertSame([[false, null]], self::suggestions($this->authGet(self::clinicPath('/booking/motifs?petId=' . $milo))));
    }

    public function testAPetTheAccountDoesNotSeeIsNotFound(): void
    {
        $stranger = $this->pet('Stranger', self::OTHER_CLIENT);

        self::assertSame('not_found', self::problemType($this->authGet(self::clinicPath('/booking/motifs?petId=' . $stranger)), 404));
        self::assertSame('validation_failed', self::problemType($this->authGet(self::clinicPath('/booking/motifs?petId=rex')), 422));
    }

    public function testThePractitionersBookableForTheMotifAreListedByName(): void
    {
        $other = $this->veterinarian('Antoine', 'Bernard');
        $motif = $this->motif('Consultation', [$this->vet, $other, Uuid::v7()->toRfc4122()]);

        self::assertSame(
            [['id' => $other, 'displayName' => 'Dr Antoine Bernard'], ['id' => $this->vet, 'displayName' => 'Dr Claire Rousseau']],
            self::list($this->authGet(self::clinicPath('/booking/practitioners?motifId=' . $motif))),
        );
    }

    public function testAnUnknownArchivedOrOfflineMotifHasNoPractitioners(): void
    {
        $motifs = [
            Uuid::v7()->toRfc4122(),
            'not-a-uuid',
            $this->motif('Ancien motif', [$this->vet], archived: true),
            $this->motif('Chirurgie', [$this->vet], online: false),
            $this->motif('Ailleurs', [$this->vet], clinicId: self::OTHER_CLINIC),
        ];

        foreach ($motifs as $motifId) {
            self::assertSame('not_found', self::problemType($this->authGet(self::clinicPath('/booking/practitioners?motifId=' . $motifId)), 404), $motifId);
        }

        self::assertSame('validation_failed', self::problemType($this->authGet(self::clinicPath('/booking/practitioners')), 422));
    }

    public function testTheAvailabilityGivesEachDayItsBookableStartsWithTheClinicOffset(): void
    {
        $this->block($this->vet, PlanningBlockType::CONSULTATION, '2026-11-02', '09:00', '14:30');
        $this->block($this->vet, PlanningBlockType::CONSULTATION, '2026-11-03', '09:00', '11:00');
        $this->block($this->vet, PlanningBlockType::CONSULTATION, '2026-11-11', '09:00', '11:00');
        // Busy 09:30–10:00 Paris on Tuesday
        $this->appointment(null, '2026-11-03 08:30:00', practitionerId: $this->vet);

        $days = self::rows(self::json($this->authGet(self::clinicPath(\sprintf(
            '/booking/availability?motifId=%s&practitionerId=%s&from=2026-11-02&to=2026-11-04',
            $this->vaccination,
            $this->vet,
        ))), 200)['days'] ?? null);

        self::assertSame(
            [
                ['date' => '2026-11-02', 'open' => true, 'slots' => [['startAt' => '2026-11-02T14:00:00+01:00']]],
                ['date' => '2026-11-03', 'open' => true, 'slots' => [
                    ['startAt' => '2026-11-03T09:00:00+01:00'],
                    ['startAt' => '2026-11-03T10:00:00+01:00'],
                    ['startAt' => '2026-11-03T10:15:00+01:00'],
                    ['startAt' => '2026-11-03T10:30:00+01:00'],
                ]],
                ['date' => '2026-11-04', 'open' => true, 'slots' => []],
            ],
            $days,
        );
    }

    public function testAClosureAndTheDaysBeyondTheHorizonAreClosed(): void
    {
        $this->enableOnlineBooking(horizonDays: 2);

        $days = self::rows(self::json($this->authGet(self::clinicPath(\sprintf(
            '/booking/availability?motifId=%s&practitionerId=%s&from=2026-11-03&to=2026-11-08',
            $this->vaccination,
            $this->vet,
        ))), 200)['days'] ?? null);

        self::assertSame(
            [['2026-11-03', true], ['2026-11-04', true], ['2026-11-05', false], ['2026-11-06', false], ['2026-11-07', false], ['2026-11-08', false]],
            array_map(static fn (array $day): array => [$day['date'], $day['open']], $days),
        );

        $this->enableOnlineBooking();
        $closure = self::rows(self::json($this->authGet(self::clinicPath(\sprintf(
            '/booking/availability?motifId=%s&practitionerId=%s&from=2026-11-10&to=2026-11-11',
            $this->vaccination,
            $this->vet,
        ))), 200)['days'] ?? null);
        self::assertSame([['2026-11-10', true], ['2026-11-11', false]], array_map(static fn (array $day): array => [$day['date'], $day['open']], $closure));
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function provideAnOutOfBoundsRangeIsAValidationFailureOnItsFieldCases(): iterable
    {
        yield 'from in the past' => ['2026-11-01', '2026-11-03', 'from', 'La date de début ne peut pas être dans le passé.'];
        yield 'from after to' => ['2026-11-05', '2026-11-03', 'to', 'La date de fin doit être postérieure ou égale à la date de début.'];
        yield '15 days' => ['2026-11-02', '2026-11-16', 'to', 'La période demandée ne peut pas dépasser 14 jours.'];
        yield 'malformed' => ['2026-02-30', '2026-11-03', 'from', 'La date doit être au format AAAA-MM-JJ.'];
    }

    #[DataProvider('provideAnOutOfBoundsRangeIsAValidationFailureOnItsFieldCases')]
    public function testAnOutOfBoundsRangeIsAValidationFailureOnItsField(string $from, string $to, string $field, string $message): void
    {
        $problem = self::json($this->authGet(self::clinicPath(\sprintf(
            '/booking/availability?motifId=%s&practitionerId=%s&from=%s&to=%s',
            $this->vaccination,
            $this->vet,
            $from,
            $to,
        ))), 422);

        self::assertSame('validation_failed', $problem['type']);
        self::assertSame('Données invalides.', $problem['title']);
        self::assertSame($field, self::firstViolationPath($problem));
        $violations = self::rows($problem['violations'] ?? null);
        self::assertSame($message, $violations[0]['message'] ?? null);
    }

    public function testFourteenDaysFromTodayAreAccepted(): void
    {
        $days = self::rows(self::json($this->authGet(self::clinicPath(\sprintf(
            '/booking/availability?motifId=%s&practitionerId=%s&from=2026-11-02&to=2026-11-15',
            $this->vaccination,
            $this->vet,
        ))), 200)['days'] ?? null);

        self::assertCount(14, $days);
    }

    public function testEveryParameterOfTheAvailabilityIsRequired(): void
    {
        self::assertSame('validation_failed', self::problemType($this->authGet(self::clinicPath('/booking/availability?motifId=' . $this->vaccination . '&from=2026-11-02&to=2026-11-03')), 422));
        self::assertSame('validation_failed', self::problemType($this->authGet(self::clinicPath('/booking/availability?motifId=' . $this->vaccination . '&practitionerId=' . $this->vet . '&from=2026-11-02')), 422));
    }

    public function testAnUnknownMotifOrAPractitionerNotBookableForItIsNotFound(): void
    {
        $other = $this->veterinarian('Antoine', 'Bernard');

        foreach ([[Uuid::v7()->toRfc4122(), $this->vet], ['nope', $this->vet], [$this->vaccination, $other], [$this->vaccination, 'nope']] as [$motifId, $practitionerId]) {
            self::assertSame('not_found', self::problemType($this->authGet(self::clinicPath(\sprintf(
                '/booking/availability?motifId=%s&practitionerId=%s&from=2026-11-02&to=2026-11-03',
                $motifId,
                $practitionerId,
            ))), 404));
        }
    }

    public function testNoBookingRouteAnswersWhileOnlineBookingIsDisabled(): void
    {
        $this->enableOnlineBooking(false);

        foreach ($this->bookingPaths(self::CLINIC) as $path) {
            self::assertSame('booking_not_open', self::problemType($this->authGet($path), 422), $path);
        }
    }

    public function testNoBookingRouteAnswersForAClinicThatIsNotActive(): void
    {
        ClinicEntityFactory::new()->withId(self::SUSPENDED_CLINIC)->withName('Clinique fermée')->suspended()->create(['timeZone' => 'Europe/Paris']);
        $this->link(self::CLIENT, self::SUSPENDED_CLINIC);
        $this->enableOnlineBooking(clinicId: self::SUSPENDED_CLINIC);

        foreach ($this->bookingPaths(self::SUSPENDED_CLINIC) as $path) {
            self::assertSame('booking_not_open', self::problemType($this->authGet($path), 422), $path);
        }
    }

    protected function phone(): string
    {
        return '+33615000401';
    }

    /**
     * @return list<string>
     */
    private function bookingPaths(string $clinicId): array
    {
        return [
            self::clinicPath('/booking/motifs', $clinicId),
            self::clinicPath('/booking/practitioners?motifId=' . $this->vaccination, $clinicId),
            self::clinicPath(\sprintf('/booking/availability?motifId=%s&practitionerId=%s&from=2026-11-02&to=2026-11-03', $this->vaccination, $this->vet), $clinicId),
        ];
    }

    /**
     * @return list<array{mixed, mixed}>
     */
    private static function suggestions(Response $response): array
    {
        return array_map(static fn (array $motif): array => [$motif['suggested'] ?? null, $motif['suggestionReason'] ?? null], self::list($response));
    }
}
