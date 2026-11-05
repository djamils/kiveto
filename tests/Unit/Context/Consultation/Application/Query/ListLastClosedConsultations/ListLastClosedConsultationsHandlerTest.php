<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Query\ListLastClosedConsultations;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\PatientIdsProviderInterface;
use App\Context\Consultation\Application\Query\ListLastClosedConsultations\LastClosedConsultation;
use App\Context\Consultation\Application\Query\ListLastClosedConsultations\ListLastClosedConsultations;
use App\Context\Consultation\Application\Query\ListLastClosedConsultations\ListLastClosedConsultationsHandler;
use App\Context\Consultation\Application\Query\SearchConsultations\ConsultationListRow;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ListLastClosedConsultationsHandlerTest extends TestCase
{
    private const string CLINIC    = '01960000-0000-7000-8000-0000000c1c01';
    private const string REX       = '0192b000-0000-7000-8000-000000000001';
    private const string LUNA      = '0192b000-0000-7000-8000-000000000002';
    private const string NALA      = '0192b000-0000-7000-8000-000000000003';
    private const string REX_FILE  = '0192e000-0000-7000-8000-000000000001';
    private const string REX_OLD   = '0192e000-0000-7000-8000-000000000002';
    private const string LUNA_FILE = '0192e000-0000-7000-8000-000000000003';

    public function testEachAnimalGetsItsLatestVisitOfAllItsPatientRecords(): void
    {
        $patients = $this->createMock(PatientIdsProviderInterface::class);
        $patients->expects(self::once())
            ->method('findAnimalIdsByPatient')
            ->with([self::REX, self::LUNA, self::NALA], self::CLINIC)
            ->willReturn([self::REX_FILE => self::REX, self::REX_OLD => self::REX, self::LUNA_FILE => self::LUNA])
        ;

        $consultations = $this->createMock(ConsultationReadRepositoryInterface::class);
        $consultations->expects(self::once())
            ->method('latestClosedForPatients')
            ->with(self::callback(static fn (ClinicId $id): bool => self::CLINIC === $id->toString()), [self::REX_FILE, self::REX_OLD, self::LUNA_FILE])
            ->willReturn([
                self::row('c-rex-1', self::REX_FILE, '2026-10-01 09:00:00', ['Vaccin']),
                // Rex's other patient record saw him later, twice at the same time: the higher id wins.
                self::row('c-rex-2', self::REX_OLD, '2026-10-20 09:00:00', ['Boiterie']),
                self::row('c-rex-3', self::REX_OLD, '2026-10-20 09:00:00', ['Contrôle']),
                self::row('c-luna', self::LUNA_FILE, '2026-09-12 14:00:00', [], 'Otite'),
            ])
        ;

        $last = new ListLastClosedConsultationsHandler($patients, $consultations)(new ListLastClosedConsultations(self::CLINIC, [self::REX, self::LUNA, self::REX, self::NALA]));

        // Nala was never seen.
        self::assertSame([self::REX, self::LUNA], array_keys($last));
        self::assertSame(['c-rex-3', self::REX, 'vet-1', '2026-10-20 09:00:00', null, ['Contrôle']], self::fields($last[self::REX]));
        self::assertSame(['c-luna', self::LUNA, 'vet-1', '2026-09-12 14:00:00', 'Otite', []], self::fields($last[self::LUNA]));
    }

    public function testNoAnimalOrNoPatientReadsNoConsultation(): void
    {
        $patients = $this->createMock(PatientIdsProviderInterface::class);
        $patients->expects(self::once())->method('findAnimalIdsByPatient')->willReturn([]);
        $consultations = $this->createMock(ConsultationReadRepositoryInterface::class);
        $consultations->expects(self::never())->method('latestClosedForPatients');

        $handler = new ListLastClosedConsultationsHandler($patients, $consultations);

        self::assertSame([], $handler(new ListLastClosedConsultations(self::CLINIC, [])));
        self::assertSame([], $handler(new ListLastClosedConsultations(self::CLINIC, [self::NALA])));
    }

    /**
     * @return list<mixed>
     */
    private static function fields(LastClosedConsultation $visit): array
    {
        return [$visit->consultationId, $visit->animalId, $visit->practitionerUserId, $visit->startedAtUtc, $visit->chiefComplaint, $visit->motifs];
    }

    /**
     * @param list<string> $motifs
     */
    private static function row(string $id, string $patientId, string $startedAt, array $motifs, ?string $complaint = null): ConsultationListRow
    {
        return new ConsultationListRow($id, $patientId, 'vet-1', 'CLOSED', $startedAt, $startedAt, $complaint, $motifs, [], [], '');
    }
}
