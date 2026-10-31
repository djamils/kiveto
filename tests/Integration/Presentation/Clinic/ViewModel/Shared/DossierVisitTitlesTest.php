<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic\ViewModel\Shared;

use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Animal\Domain\ValueObject\OwnershipRole;
use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId as ClientClinicId;
use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Animal\Factory\OwnershipEntityFactory;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Patient\Factory\PatientEntityFactory;
use App\Presentation\Clinic\ViewModel\Animal\AnimalSheetBuilder;
use App\Presentation\Clinic\ViewModel\Client\ClientSheetBuilder;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * Regression of the visit titles of the animal and client sheets once the
 * rule moved to Consultation's `VisitTitle` (Portal API Task 12).
 */
final class DossierVisitTitlesTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '01960000-0000-7000-8000-0000000712c1';
    private const string CLIENT_ID = '01960000-0000-7000-8000-0000000712d1';
    private const string ANIMAL_ID = '01960000-0000-7000-8000-0000000712a1';
    private const string USER_ID   = '01960000-0000-7000-8000-0000000712e1';

    private const array EXPECTED = [
        'Consultation',
        'Toux persistante',
        'Vaccination et bilan annuel',
    ];

    public function testTheAnimalSheetTitlesItsVisits(): void
    {
        $this->seed();

        $queryBus = self::getContainer()->get(QueryBusInterface::class);
        \assert($queryBus instanceof QueryBusInterface);
        $animal = $queryBus->ask(new GetAnimalById(self::CLINIC_ID, self::ANIMAL_ID));
        \assert($animal instanceof AnimalView);

        $builder = self::getContainer()->get(AnimalSheetBuilder::class);
        \assert($builder instanceof AnimalSheetBuilder);

        $sheet = $builder->build($animal, new \DateTimeZone('Europe/Paris'), new \DateTimeImmutable('2026-04-20 10:00:00'));

        self::assertSame(self::EXPECTED, self::titles($sheet));
    }

    public function testTheClientSheetTitlesItsVisits(): void
    {
        $this->seed();

        $clients = self::getContainer()->get(ClientReadRepositoryInterface::class);
        \assert($clients instanceof ClientReadRepositoryInterface);
        $client = $clients->findById(ClientClinicId::fromString(self::CLINIC_ID), ClientId::fromString(self::CLIENT_ID));
        \assert(null !== $client);

        $builder = self::getContainer()->get(ClientSheetBuilder::class);
        \assert($builder instanceof ClientSheetBuilder);

        $sheet = $builder->build($client, new \DateTimeZone('Europe/Paris'), new \DateTimeImmutable('2026-04-20 10:00:00'));

        self::assertSame(self::EXPECTED, self::titles($sheet));
    }

    /**
     * @param array<string, mixed> $sheet
     *
     * @return list<string> titles of the consultation visits, newest first
     */
    private static function titles(array $sheet): array
    {
        $visits = $sheet['visits'];
        \assert(\is_array($visits));

        $titles = [];

        foreach ($visits as $visit) {
            \assert(\is_array($visit) && \is_string($visit['id']));

            if (str_starts_with($visit['id'], 'c-')) {
                \assert(\is_string($visit['t']));
                $titles[] = $visit['t'];
            }
        }

        return $titles;
    }

    /**
     * A client's dog with three closed consultations on two patient rows: one
     * with motifs, one with a chief complaint only, one with neither.
     */
    private function seed(): void
    {
        ClientEntityFactory::new()->withId(self::CLIENT_ID)->withClinicId(self::CLINIC_ID)->withName('Claire', 'Rostand')->active()->since('2026-01-01')->create();
        AnimalEntityFactory::new()->withClinicId(self::CLINIC_ID)->withName('Oslo')->dog()->active()->alive()->create(['id' => Uuid::fromString(self::ANIMAL_ID)]);
        OwnershipEntityFactory::createOne([
            'animal'   => AnimalEntityFactory::find(['id' => Uuid::fromString(self::ANIMAL_ID)]),
            'clientId' => Uuid::fromString(self::CLIENT_ID),
            'role'     => OwnershipRole::PRIMARY,
        ]);

        $patients = [Uuid::v7()->toRfc4122(), Uuid::v7()->toRfc4122()];

        foreach ($patients as $patientId) {
            PatientEntityFactory::new()->withId($patientId)->withClinicId(self::CLINIC_ID)->identified()->withAnimalLinkId(self::ANIMAL_ID)->create();
        }

        $this->consultation($patients[0], '2026-04-10 08:00:00', ['Vaccination', 'Bilan annuel'], 'Ignorée, des motifs existent.');
        $this->consultation($patients[1], '2026-04-11 08:00:00', [], 'Toux persistante.');
        $this->consultation($patients[0], '2026-04-12 08:00:00', [], null);
    }

    /**
     * @param list<string> $motifs
     */
    private function consultation(string $patientId, string $startedAt, array $motifs, ?string $chiefComplaint): void
    {
        $repository = self::getContainer()->get(ConsultationRepositoryInterface::class);
        \assert($repository instanceof ConsultationRepositoryInterface);

        $at           = new \DateTimeImmutable($startedAt);
        $consultation = Consultation::startFromAdmission(
            ConsultationId::fromString(Uuid::v7()->toRfc4122()),
            ClinicId::fromString(self::CLINIC_ID),
            AdmissionId::fromString(Uuid::v7()->toRfc4122()),
            PatientId::fromString($patientId),
            UserId::fromString(self::USER_ID),
            $at,
        );

        if ([] !== $motifs) {
            $consultation->setMotifs($motifs, $at);
        }

        if (null !== $chiefComplaint) {
            $consultation->recordChiefComplaint($chiefComplaint, $at);
        }

        $consultation->close(UserId::fromString(self::USER_ID), null, $at->modify('+30 minutes'));
        $repository->save($consultation);
    }
}
