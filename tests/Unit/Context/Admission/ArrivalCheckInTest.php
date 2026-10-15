<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Admission;

use App\Context\Admission\Application\Command\RecordArrivalCheckIn\RecordArrivalCheckIn;
use App\Context\Admission\Application\Command\RecordArrivalCheckIn\RecordArrivalCheckInHandler;
use App\Context\Admission\Domain\Admission;
use App\Context\Admission\Domain\Exception\AdmissionAlreadyClosedException;
use App\Context\Admission\Domain\Repository\AdmissionRepositoryInterface;
use App\Context\Admission\Domain\ValueObject\AdmissionId;
use App\Context\Admission\Domain\ValueObject\ArrivalCheckIn;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Context\Admission\Domain\ValueObject\ClosureReason;
use App\Context\Admission\Domain\ValueObject\IntakeChannel;
use App\Context\Admission\Domain\ValueObject\TriageLevel;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

/**
 * The desk's arrival check-in of a booked patient: weighing, four flags and
 * a short note, kept on the admission for the practitioner.
 */
final class ArrivalCheckInTest extends TestCase
{
    private const string CLINIC_ID    = '12345678-9abc-def0-1234-56789abcdef0';
    private const string ADMISSION_ID = '01234567-89ab-cdef-0123-456789abcdef';

    public function testTheNoteIsTidiedAndAnEmptyOneIsNoNote(): void
    {
        $checkIn = ArrivalCheckIn::record(true, false, true, false, 4200, "  boite depuis\n hier   soir ");

        self::assertTrue($checkIn->fasting);
        self::assertFalse($checkIn->newSymptom);
        self::assertTrue($checkIn->documentsBrought);
        self::assertFalse($checkIn->ongoingTreatment);
        self::assertSame(4200, $checkIn->weightGrams);
        self::assertSame('boite depuis hier soir', $checkIn->note);
        self::assertNull(ArrivalCheckIn::record(false, false, false, false, null, '   ')->note);
        self::assertNull(ArrivalCheckIn::record(false, false, false, false, null, null)->note);
    }

    public function testANoteLongerThan140CharactersIsRefused(): void
    {
        self::assertSame(140, mb_strlen((string) ArrivalCheckIn::record(false, false, false, false, null, str_repeat('é', 140))->note));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('140 caractères');

        ArrivalCheckIn::record(false, false, false, false, null, str_repeat('é', 141));
    }

    public function testTheDeskScaleStopsAt120Kilos(): void
    {
        self::assertSame(120_000, ArrivalCheckIn::record(false, false, false, false, 120_000, null)->weightGrams);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('120 kg');

        ArrivalCheckIn::record(false, false, false, false, 120_001, null);
    }

    public function testAZeroWeightIsATypingError(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ArrivalCheckIn::record(false, false, false, false, 0, null);
    }

    public function testItTravelsAsAnArray(): void
    {
        $checkIn = ArrivalCheckIn::record(false, true, false, true, 31_200, 'mange moins');

        self::assertSame([
            'fasting'          => false,
            'newSymptom'       => true,
            'documentsBrought' => false,
            'ongoingTreatment' => true,
            'weightGrams'      => 31_200,
            'note'             => 'mange moins',
        ], $checkIn->toArray());
        self::assertSame($checkIn->toArray(), ArrivalCheckIn::fromArray($checkIn->toArray())->toArray());
        self::assertSame(ArrivalCheckIn::record(false, false, false, false, null, null)->toArray(), ArrivalCheckIn::fromArray([])->toArray());
    }

    public function testTheAdmissionKeepsTheLatestCheckIn(): void
    {
        $admission = $this->admission();
        $later     = new \DateTimeImmutable('2024-06-01T10:05:00+00:00');

        self::assertNull($admission->arrivalCheckIn());

        $admission->recordArrivalCheckIn(ArrivalCheckIn::record(true, false, false, false, null, null), $later);
        $admission->recordArrivalCheckIn(ArrivalCheckIn::record(false, true, false, false, 6400, 'vomit'), $later);

        self::assertSame(6400, $admission->arrivalCheckIn()?->weightGrams);
        self::assertFalse($admission->arrivalCheckIn()->fasting);
        self::assertSame($later, $admission->updatedAt());
    }

    public function testAClosedVisitTakesNoCheckIn(): void
    {
        $admission = $this->admission();
        $admission->close(ClosureReason::ConsultationCompleted, new \DateTimeImmutable('2024-06-01T10:30:00+00:00'));

        $this->expectException(AdmissionAlreadyClosedException::class);

        $admission->recordArrivalCheckIn(ArrivalCheckIn::record(true, false, false, false, null, null), new \DateTimeImmutable());
    }

    public function testAChipReadAtTheDeskIsKeptForAnUnidentifiedPatient(): void
    {
        self::assertSame('250268500112847', $this->admission('250268500112847')->scannedMicrochip());
        self::assertNull($this->admission()->scannedMicrochip());
    }

    public function testAChipHasFifteenDigits(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('15 chiffres');

        $this->admission('25026850011');
    }

    public function testTheHandlerRecordsTheCheckInAndSavesTheVisit(): void
    {
        $admission = $this->admission();

        $repository = $this->createMock(AdmissionRepositoryInterface::class);
        $repository->expects(self::once())->method('get')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (AdmissionId $id): bool => self::ADMISSION_ID === $id->value()),
            )
            ->willReturn($admission)
        ;
        $repository->expects(self::once())->method('save')->with($admission);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2024-06-01T10:02:00+00:00'));

        (new RecordArrivalCheckInHandler($repository, $clock))(new RecordArrivalCheckIn(
            clinicId: self::CLINIC_ID,
            admissionId: self::ADMISSION_ID,
            fasting: true,
            newSymptom: true,
            documentsBrought: true,
            ongoingTreatment: true,
            weightGrams: 4100,
            note: 'Ordonnance du confrère',
        ));

        self::assertSame(
            ArrivalCheckIn::record(true, true, true, true, 4100, 'Ordonnance du confrère')->toArray(),
            $admission->arrivalCheckIn()?->toArray(),
        );
    }

    private function admission(?string $scannedMicrochip = null): Admission
    {
        return Admission::open(
            id: AdmissionId::fromString(self::ADMISSION_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            patientId: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            isPatientIdentified: null === $scannedMicrochip,
            channel: IntakeChannel::AppointmentBooked,
            triage: TriageLevel::Standard,
            presenter: null,
            now: new \DateTimeImmutable('2024-06-01T10:00:00+00:00'),
            scannedMicrochip: $scannedMicrochip,
        );
    }
}
