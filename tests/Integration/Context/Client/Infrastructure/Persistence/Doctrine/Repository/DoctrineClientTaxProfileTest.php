<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Application\Query\SearchClients\ClientListItemView;
use App\Context\Client\Application\Query\SearchClients\SearchClientsCriteria;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClientTaxProfile;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\Siren;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * The tax profile of a structure: persisted, read back, searched and sorted.
 */
final class DoctrineClientTaxProfileTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID       = '01960000-0000-7000-8000-0000000001c1';
    private const string OTHER_CLINIC_ID = '01960000-0000-7000-8000-0000000001c2';

    private const string CALANQUES = '01960000-0000-7000-8000-0000000001d1';
    private const string AUBAGNE   = '01960000-0000-7000-8000-0000000001d2';
    private const string REFUGE    = '01960000-0000-7000-8000-0000000001d3';
    private const string MARTIN    = '01960000-0000-7000-8000-0000000001d4';
    private const string ELSEWHERE = '01960000-0000-7000-8000-0000000001d5';

    public function testSaveWritesAndUpdatesTheTaxColumns(): void
    {
        $repository = $this->writeRepository();
        $siren      = Siren::fromInput('523456788');
        $client     = Client::create(
            ClientId::fromString(self::CALANQUES),
            ClinicId::fromString(self::CLINIC_ID),
            new ClientIdentity('Claire', 'Rostand'),
            [ContactMethod::email(EmailAddress::fromString('claire@calanques.fr'), ContactLabel::WORK, true)],
            new \DateTimeImmutable('2026-10-17 10:00:00'),
            ClientTaxProfile::frenchBusiness('Élevage des Calanques', $siren, null),
        );
        $repository->save($client);
        $this->clearEntityManager();

        $loaded = $repository->get(ClinicId::fromString(self::CLINIC_ID), ClientId::fromString(self::CALANQUES));
        self::assertTrue($loaded->taxProfile()->equals($client->taxProfile()));

        $updated = ClientTaxProfile::frenchBusiness('Élevage des Calanques SARL', $siren, FrenchVatNumber::forSiren($siren));
        $loaded->updateTaxProfile($updated, new \DateTimeImmutable('2026-10-17 11:00:00'));
        $repository->save($loaded);
        $this->clearEntityManager();

        $view = $this->readRepository()->findById(ClinicId::fromString(self::CLINIC_ID), ClientId::fromString(self::CALANQUES));
        self::assertNotNull($view);
        self::assertSame('french_business', $view->taxStatus);
        self::assertSame('Élevage des Calanques SARL', $view->legalName);
        self::assertSame('523456788', $view->siren);
        self::assertSame('FR31523456788', $view->vatNumber);
    }

    public function testAPlainClientReadsAsAnIndividual(): void
    {
        $this->seed();

        $view = $this->readRepository()->findById(ClinicId::fromString(self::CLINIC_ID), ClientId::fromString(self::MARTIN));

        self::assertNotNull($view);
        self::assertSame('individual', $view->taxStatus);
        self::assertNull($view->legalName);
        self::assertNull($view->siren);
        self::assertNull($view->vatNumber);
    }

    public function testFindIdsBySirenIncludesArchivedClientsOfTheClinicOnly(): void
    {
        $this->seed();

        self::assertSame(
            [self::CALANQUES, self::AUBAGNE],
            $this->readRepository()->findIdsBySiren(ClinicId::fromString(self::CLINIC_ID), '523456788'),
        );
        self::assertSame([self::ELSEWHERE], $this->readRepository()->findIdsBySiren(ClinicId::fromString(self::OTHER_CLINIC_ID), '523456788'));
        self::assertSame([], $this->readRepository()->findIdsBySiren(ClinicId::fromString(self::CLINIC_ID), '812345676'));
    }

    public function testTheFreeTextMatchesTheLegalNameAndTheExactSiren(): void
    {
        $this->seed();

        self::assertSame([self::CALANQUES, self::AUBAGNE], $this->ids(new SearchClientsCriteria(searchTerm: 'Calanques')));
        self::assertSame([self::CALANQUES, self::AUBAGNE], $this->ids(new SearchClientsCriteria(searchTerm: '523 456 788')));
        self::assertSame([], $this->ids(new SearchClientsCriteria(searchTerm: '523456')));
    }

    public function testStructuresOnlyKeepsClientsWithALegalName(): void
    {
        $this->seed();

        $result = $this->readRepository()->search(ClinicId::fromString(self::CLINIC_ID), new SearchClientsCriteria(withLegalNameOnly: true));

        self::assertSame([self::CALANQUES, self::AUBAGNE, self::REFUGE], array_map(static fn (ClientListItemView $i): string => $i->id, $result['items']));
        self::assertSame('Élevage des Calanques', $result['items'][0]->legalName);
        self::assertSame(3, $this->readRepository()->countBy(ClinicId::fromString(self::CLINIC_ID), new SearchClientsCriteria(withLegalNameOnly: true)));
    }

    public function testAStructureSortsByItsLegalName(): void
    {
        $this->seed();

        // « Élevage… » sorts at E, before « Martin »; « Refuge… » at R, after it.
        self::assertSame(
            [self::CALANQUES, self::AUBAGNE, self::MARTIN, self::REFUGE],
            $this->ids(new SearchClientsCriteria()),
        );
        self::assertSame(
            [self::REFUGE, self::MARTIN, self::AUBAGNE, self::CALANQUES],
            $this->ids(new SearchClientsCriteria(direction: 'desc')),
        );
    }

    /**
     * @return list<string>
     */
    private function ids(SearchClientsCriteria $criteria): array
    {
        return array_map(
            static fn (ClientListItemView $item): string => $item->id,
            $this->readRepository()->search(ClinicId::fromString(self::CLINIC_ID), $criteria)['items'],
        );
    }

    private function seed(): void
    {
        ClientEntityFactory::new()->withId(self::CALANQUES)->withClinicId(self::CLINIC_ID)->withName('Claire', 'Rostand')
            ->withTaxProfile(ClientTaxStatus::FRENCH_BUSINESS, 'Élevage des Calanques', '523456788', 'FR31523456788')
            ->active()->since('2026-01-01')->create()
        ;
        ClientEntityFactory::new()->withId(self::AUBAGNE)->withClinicId(self::CLINIC_ID)->withName('Paul', 'Rostand')
            ->withTaxProfile(ClientTaxStatus::FRENCH_BUSINESS, 'Élevage des Calanques — site d\'Aubagne', '523456788')
            ->archived()->since('2026-02-01')->create()
        ;
        ClientEntityFactory::new()->withId(self::REFUGE)->withClinicId(self::CLINIC_ID)->withName('Inès', 'Perrin')
            ->withTaxProfile(ClientTaxStatus::INDIVIDUAL, 'Refuge des Collines')
            ->active()->since('2026-03-01')->create()
        ;
        ClientEntityFactory::new()->withId(self::MARTIN)->withClinicId(self::CLINIC_ID)->withName('Alice', 'Martin')
            ->active()->since('2026-04-01')->create()
        ;
        ClientEntityFactory::new()->withId(self::ELSEWHERE)->withClinicId(self::OTHER_CLINIC_ID)->withName('Léa', 'Autre')
            ->withTaxProfile(ClientTaxStatus::FRENCH_BUSINESS, 'Calanques Bis', '523456788')
            ->active()->since('2026-01-01')->create()
        ;
    }

    private function clearEntityManager(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $em->clear();
    }

    private function writeRepository(): ClientRepositoryInterface
    {
        $repository = self::getContainer()->get(ClientRepositoryInterface::class);
        \assert($repository instanceof ClientRepositoryInterface);

        return $repository;
    }

    private function readRepository(): ClientReadRepositoryInterface
    {
        $repository = self::getContainer()->get(ClientReadRepositoryInterface::class);
        \assert($repository instanceof ClientReadRepositoryInterface);

        return $repository;
    }
}
