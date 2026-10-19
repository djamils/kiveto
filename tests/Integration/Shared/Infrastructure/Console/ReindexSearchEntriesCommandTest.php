<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Console;

use App\Context\Animal\Domain\ValueObject\Species;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Animal\Factory\OwnershipEntityFactory;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Client\Factory\ContactMethodEntityFactory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;

/**
 * `app:search:reindex` rebuilds both search indexes from the aggregates: a
 * structure under its legal name, and its animals under it too.
 */
final class ReindexSearchEntriesCommandTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '01960000-0000-7000-8000-0000000003d1';
    private const string STRUCTURE = '01960000-0000-7000-8000-0000000003d2';
    private const string PERSON    = '01960000-0000-7000-8000-0000000003d3';
    private const string HORSE     = '01960000-0000-7000-8000-0000000003d4';
    private const string STRAY     = '01960000-0000-7000-8000-0000000003d5';

    public function testBothIndexesAreRebuiltWithTheLegalName(): void
    {
        ClientEntityFactory::new()->withId(self::STRUCTURE)->withClinicId(self::CLINIC_ID)->withName('Claire', 'Rostand')->active()
            ->withTaxProfile(ClientTaxStatus::FRENCH_BUSINESS, 'Élevage des Calanques', '523456788')->create()
        ;
        ContactMethodEntityFactory::new()->forClient(self::STRUCTURE)->work()->phone('+33491223344')->primary()->create();
        ContactMethodEntityFactory::new()->forClient(self::STRUCTURE)->email('contact@calanques.fr')->primary()->create();
        ClientEntityFactory::new()->withId(self::PERSON)->withClinicId(self::CLINIC_ID)->withName('Nadia', 'Benali')->archived()->create();

        AnimalEntityFactory::new()->withId(self::HORSE)->withClinicId(self::CLINIC_ID)->withName('Tempête')->active()
            ->withBreed('Camargue', Species::NAC)->withMicrochip('250269802120045')->create()
        ;
        OwnershipEntityFactory::new()->withAnimalId(self::HORSE)->withClientId(self::STRUCTURE)->primary()->active()->create();
        AnimalEntityFactory::new()->withId(self::STRAY)->withClinicId(self::CLINIC_ID)->withName('Moustache')->active()
            ->withBreed('', Species::CAT)->with(['microchipNumber' => null])->create()
        ;

        $application = new Application(self::bootKernel());
        $tester      = new CommandTester($application->find('app:search:reindex'));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Re-indexation terminée.', $tester->getDisplay());

        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        $clients = $connection->fetchAllAssociativeIndexed(
            'SELECT BIN_TO_UUID(id) AS id, legal_name, search_name, search_phone, primary_email, status
             FROM client__search_entries WHERE clinic_id = UUID_TO_BIN(:clinic)',
            ['clinic' => self::CLINIC_ID],
        );
        self::assertSame(
            ['legal_name' => 'Élevage des Calanques', 'search_name' => 'elevage des calanques claire rostand', 'search_phone' => '+33491223344', 'primary_email' => 'contact@calanques.fr', 'status' => 'active'],
            $clients[self::STRUCTURE],
        );
        self::assertSame(
            ['legal_name' => null, 'search_name' => 'nadia benali', 'search_phone' => null, 'primary_email' => null, 'status' => 'archived'],
            $clients[self::PERSON],
        );

        $animals = $connection->fetchAllAssociativeIndexed(
            'SELECT BIN_TO_UUID(id) AS id, search_owner_name, search_phone, breed_name, search_chip, BIN_TO_UUID(primary_owner_client_id) AS owner
             FROM animal__search_entries WHERE clinic_id = UUID_TO_BIN(:clinic)',
            ['clinic' => self::CLINIC_ID],
        );
        self::assertSame('Élevage des Calanques', $animals[self::HORSE]['search_owner_name']);
        self::assertSame('Camargue', $animals[self::HORSE]['breed_name']);
        self::assertSame('250269802120045', $animals[self::HORSE]['search_chip']);
        self::assertSame(self::STRUCTURE, $animals[self::HORSE]['owner']);
        self::assertNotNull($animals[self::HORSE]['search_phone']);
        self::assertSame(['search_owner_name' => null, 'search_phone' => null, 'breed_name' => null, 'search_chip' => null, 'owner' => null], $animals[self::STRAY]);
    }
}
