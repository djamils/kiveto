<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Consultation\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\PatientIdsProviderInterface;
use App\Context\Consultation\Application\Query\SearchConsultations\ConsultationListRow;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationStatus;
use App\Fixtures\Context\Consultation\Factory\ConsultationEntityFactory;
use App\Fixtures\Context\Patient\Factory\PatientEntityFactory;
use App\Tests\Support\Database\QueryCounter;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/** The latest closed consultation of each patient, with its motifs, read at once. */
final class LatestClosedConsultationsTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC   = '01960000-0000-7000-8000-0000000c1c01';
    private const string OTHER    = '01960000-0000-7000-8000-0000000c1c02';
    private const string REX      = '0192e000-0000-7000-8000-0000000000e1';
    private const string LUNA     = '0192e000-0000-7000-8000-0000000000e2';
    private const string NALA     = '0192e000-0000-7000-8000-0000000000e3';
    private const string LATEST   = '0192f000-0000-7000-8000-0000000000f2';
    private const string TWIN_LOW = '0192f000-0000-7000-8000-0000000000f4';
    private const string TWIN     = '0192f000-0000-7000-8000-0000000000f5';
    private const string VET      = '0192a000-0000-7000-8000-0000000000a1';

    public function testTheLatestClosedConsultationOfEachPatientInTwoQueries(): void
    {
        $this->consultation('0192f000-0000-7000-8000-0000000000f1', self::REX, '2026-09-01 09:00:00');
        $this->consultation(self::LATEST, self::REX, '2026-10-01 09:00:00');
        // Still open, or of another clinic: never a last visit.
        $this->consultation('0192f000-0000-7000-8000-0000000000f3', self::REX, '2026-10-30 09:00:00', ConsultationStatus::OPEN);
        $this->consultation('0192f000-0000-7000-8000-0000000000f6', self::REX, '2026-10-31 09:00:00', clinicId: self::OTHER);
        // Two at the same time: the higher id wins.
        $this->consultation(self::TWIN_LOW, self::LUNA, '2026-10-10 10:00:00');
        $this->consultation(self::TWIN, self::LUNA, '2026-10-10 10:00:00');
        $this->motif(self::LATEST, 'Vaccin', 2);
        $this->motif(self::LATEST, 'Rappel', 1);

        QueryCounter::reset();
        $rows    = $this->consultations()->latestClosedForPatients(ClinicId::fromString(self::CLINIC), [self::REX, self::LUNA, self::NALA]);
        $queries = QueryCounter::count();

        $byPatient = [];
        foreach ($rows as $row) {
            $byPatient[$row->patientId] = $row;
        }
        ksort($byPatient);

        self::assertSame([self::REX, self::LUNA], array_keys($byPatient));
        self::assertSame([self::LATEST, self::VET, 'CLOSED', '2026-10-01 09:00:00', ['Rappel', 'Vaccin']], self::fields($byPatient[self::REX]));
        self::assertSame([self::TWIN, self::VET, 'CLOSED', '2026-10-10 10:00:00', []], self::fields($byPatient[self::LUNA]));
        self::assertSame(2, $queries, 'the consultations, then their motifs');
    }

    public function testThePatientRecordsOfSeveralAnimals(): void
    {
        $rex  = '0192b000-0000-7000-8000-0000000000c1';
        $luna = '0192b000-0000-7000-8000-0000000000c2';
        PatientEntityFactory::new()->withId(self::REX)->withClinicId(self::CLINIC)->identified()->withAnimalLinkId($rex)->active()->create();
        PatientEntityFactory::new()->withId(self::NALA)->withClinicId(self::CLINIC)->identified()->withAnimalLinkId($rex)->active()->create();
        PatientEntityFactory::new()->withId(self::LUNA)->withClinicId(self::OTHER)->identified()->withAnimalLinkId($luna)->active()->create();

        $provider = self::getContainer()->get(PatientIdsProviderInterface::class);
        \assert($provider instanceof PatientIdsProviderInterface);
        $byPatient = $provider->findAnimalIdsByPatient([$rex, $luna], self::CLINIC);
        ksort($byPatient);

        // Rex is behind two records; Luna's is another clinic's.
        self::assertSame([self::REX => $rex, self::NALA => $rex], $byPatient);
    }

    /**
     * @return list<mixed>
     */
    private static function fields(ConsultationListRow $row): array
    {
        return [$row->consultationId, $row->practitionerUserId, $row->status, $row->startedAtUtc, $row->motifs];
    }

    private function consultation(string $id, string $patientId, string $startedAt, ConsultationStatus $status = ConsultationStatus::CLOSED, string $clinicId = self::CLINIC): void
    {
        $at = new \DateTimeImmutable($startedAt, new \DateTimeZone('UTC'));

        ConsultationEntityFactory::new()
            ->withId($id)
            ->withClinicId($clinicId)
            ->withPatientId($patientId)
            ->withPractitionerUserId(self::VET)
            ->withStatus($status)
            ->create(['startedAtUtc' => $at, 'closedAtUtc' => ConsultationStatus::CLOSED === $status ? $at->modify('+20 minutes') : null])
        ;
    }

    private function motif(string $consultationId, string $label, int $position): void
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->insert('consultation__motifs', [
            'id'              => Uuid::v7()->toBinary(),
            'consultation_id' => Uuid::fromString($consultationId)->toBinary(),
            'label'           => $label,
            'position'        => $position,
        ]);
    }

    private function consultations(): ConsultationReadRepositoryInterface
    {
        $repository = self::getContainer()->get(ConsultationReadRepositoryInterface::class);
        \assert($repository instanceof ConsultationReadRepositoryInterface);

        return $repository;
    }
}
