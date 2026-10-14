<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Animal\Infrastructure\Persistence;

use App\Context\Animal\Application\Port\AnimalDirectoryReadRepositoryInterface;
use App\Context\Animal\Application\Query\ListAnimalDirectoryFacts\DirectoryAlertView;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\PreventiveCareKind;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Animal\Factory\PreventiveCareRecordEntityFactory;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * The facets of the directory (Répertoire): free text, species, medical alert
 * and due reminder, plus the alerts and reminders shown on each row.
 */
final class DbalAnimalDirectoryReadRepositoryTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID       = 'c3c3c3c3-c3c3-4c3c-8c3c-c3c3c3c3c3c3';
    private const string OTHER_CLINIC_ID = 'c4c4c4c4-c4c4-4c4c-8c4c-c4c4c4c4c4c4';

    private const string REX   = 'b1b1b1b1-0000-4000-8000-000000000001';
    private const string MINOU = 'b1b1b1b1-0000-4000-8000-000000000002';
    private const string NALA  = 'b1b1b1b1-0000-4000-8000-000000000003';
    private const string AWAY  = 'b1b1b1b1-0000-4000-8000-000000000004';

    private const string MARIE  = 'd1d1d1d1-0000-4000-8000-000000000001';
    private const string SOPHIE = 'd1d1d1d1-0000-4000-8000-000000000002';

    private Connection $connection;

    private AnimalDirectoryReadRepositoryInterface $repository;

    private \DateTimeImmutable $dueBy;

    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        \assert($entityManager instanceof EntityManagerInterface);
        $this->connection = $entityManager->getConnection();

        $repository = self::getContainer()->get(AnimalDirectoryReadRepositoryInterface::class);
        \assert($repository instanceof AnimalDirectoryReadRepositoryInterface);
        $this->repository = $repository;

        $this->dueBy = new \DateTimeImmutable('2026-09-30');

        $this->animal(self::REX, self::CLINIC_ID, 'Rex', 'dog', '250269812345678', self::MARIE);
        $this->animal(self::MINOU, self::CLINIC_ID, 'Minou', 'cat', null, self::MARIE);
        $this->animal(self::NALA, self::CLINIC_ID, 'Nala', 'dog', null, null);
        $this->animal(self::AWAY, self::OTHER_CLINIC_ID, 'Rex', 'dog', null, self::SOPHIE);

        $this->alert(self::REX, 'CHRONIC_CONDITION', 'Arthrose');
        $this->alert(self::REX, 'ALLERGY', 'Pénicilline');
        $this->alert(self::AWAY, 'ALLERGY', 'Pénicilline');

        // Minou: the CHPPiL came due, then was renewed — only the rage is due.
        $this->act(self::MINOU, 'CHPPiL', '2025-08-01', '2026-08-01');
        $this->act(self::MINOU, 'chppil', '2026-08-10', '2027-08-10');
        $this->act(self::MINOU, 'Rage', '2025-09-15', '2026-09-15');
        // Nala: due after the horizon, or never chased.
        $this->act(self::NALA, 'Rage', '2025-12-01', '2026-12-01');
        $this->act(self::NALA, 'Leishmaniose', '2026-01-01', null);
    }

    public function testWithoutFacetsEveryAnimalOfTheClinicMatches(): void
    {
        $matches = $this->repository->matches($this->clinic(), null, [], false, false, $this->dueBy);

        self::assertSame([self::REX, self::MINOU, self::NALA], $this->sorted($matches->animalIds()));
        // Owners come once each, and an animal without one adds nobody.
        self::assertSame([self::MARIE], $matches->ownerIds());
    }

    public function testTheTermReachesTheChipNumber(): void
    {
        $matches = $this->repository->matches($this->clinic(), '2502 6981', [], false, false, $this->dueBy);

        self::assertSame([self::REX], $matches->animalIds());
    }

    public function testTheTermReachesTheName(): void
    {
        self::assertSame(
            [self::MINOU],
            $this->repository->matches($this->clinic(), 'Min', [], false, false, $this->dueBy)->animalIds(),
        );
    }

    public function testSpeciesNarrowDown(): void
    {
        $matches = $this->repository->matches($this->clinic(), null, ['cat'], false, false, $this->dueBy);

        self::assertSame([self::MINOU], $matches->animalIds());
    }

    public function testMedicalAlertFlag(): void
    {
        self::assertSame(
            [self::REX],
            $this->repository->matches($this->clinic(), null, [], true, false, $this->dueBy)->animalIds(),
        );
    }

    public function testDueReminderFlagOnlyLooksAtTheLatestActOfAProtocol(): void
    {
        self::assertSame(
            [self::MINOU],
            $this->repository->matches($this->clinic(), null, [], false, true, $this->dueBy)->animalIds(),
        );
    }

    public function testFlagsAreAlternatives(): void
    {
        self::assertSame(
            [self::REX, self::MINOU],
            $this->sorted($this->repository->matches($this->clinic(), null, [], true, true, $this->dueBy)->animalIds()),
        );
    }

    public function testFactsCarryAlertsAndDueReminders(): void
    {
        $facts = $this->repository->facts($this->clinic(), [self::REX, self::MINOU, self::NALA, self::AWAY], $this->dueBy);

        self::assertSame([self::REX, self::MINOU], array_keys($facts));
        self::assertSame(
            [['ALLERGY', 'Pénicilline'], ['CHRONIC_CONDITION', 'Arthrose']],
            array_map(static fn (DirectoryAlertView $a): array => [$a->kind, $a->label], $facts[self::REX]->alerts),
        );
        self::assertSame([], $facts[self::REX]->dueReminders);
        self::assertSame([], $facts[self::MINOU]->alerts);
        self::assertCount(1, $facts[self::MINOU]->dueReminders);
        self::assertSame('Rage', $facts[self::MINOU]->dueReminders[0]->label);
        self::assertSame('2026-09-15', $facts[self::MINOU]->dueReminders[0]->nextDueOn->format('Y-m-d'));
    }

    public function testNoAnimalMeansNoFacts(): void
    {
        self::assertSame([], $this->repository->facts($this->clinic(), [], $this->dueBy));
    }

    private function clinic(): ClinicId
    {
        return ClinicId::fromString(self::CLINIC_ID);
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function sorted(array $ids): array
    {
        sort($ids);

        return $ids;
    }

    private function animal(string $id, string $clinicId, string $name, string $species, ?string $chip, ?string $ownerId): void
    {
        $factory = AnimalEntityFactory::new()->withId($id)->withClinicId($clinicId)->withName($name);
        ('cat' === $species ? $factory->cat() : $factory->dog())->create();

        $this->connection->executeStatement(
            'INSERT INTO animal__search_entries
                (id, clinic_id, animal_name, search_name, search_chip, search_phone,
                 species, breed_name, search_owner_name, primary_owner_client_id, status, updated_at)
             VALUES (:id, :clinicId, :name, :searchName, :chip, NULL,
                 :species, NULL, NULL, :owner, :status, NOW())',
            [
                'id'         => Uuid::fromString($id)->toBinary(),
                'clinicId'   => Uuid::fromString($clinicId)->toBinary(),
                'name'       => $name,
                'searchName' => mb_strtolower($name),
                'chip'       => $chip,
                'species'    => $species,
                'owner'      => null === $ownerId ? null : Uuid::fromString($ownerId)->toBinary(),
                'status'     => 'active',
            ],
        );
    }

    private function alert(string $animalId, string $kind, string $label): void
    {
        $this->connection->executeStatement(
            'INSERT INTO animal__medical_alerts (id, kind, label, note, animal_id) VALUES (:id, :kind, :label, NULL, :animal)',
            [
                'id'     => Uuid::v7()->toBinary(),
                'kind'   => $kind,
                'label'  => $label,
                'animal' => Uuid::fromString($animalId)->toBinary(),
            ],
        );
    }

    private function act(string $animalId, string $label, string $administered, ?string $nextDue): void
    {
        PreventiveCareRecordEntityFactory::new()
            ->forAnimal(self::CLINIC_ID, $animalId)
            ->act(
                PreventiveCareKind::VACCINATION,
                $label,
                new \DateTimeImmutable($administered),
                null === $nextDue ? null : new \DateTimeImmutable($nextDue),
            )
            ->create()
        ;
    }
}
