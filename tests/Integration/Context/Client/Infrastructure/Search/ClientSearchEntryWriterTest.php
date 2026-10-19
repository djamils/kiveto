<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Infrastructure\Search;

use App\Context\Client\Application\Search\ClientSearchEntryData;
use App\Context\Client\Application\Search\ClientSearchEntryWriterInterface;
use App\Context\Client\Application\Search\ClientSearchRepositoryInterface;
use App\Context\Client\Application\Search\ClientSearchRow;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Entity\SearchEntryEntity;
use App\Shared\Search\Query\SearchQuery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The client search index (`client__search_entries`): what the writer stores
 * is read back from the database, as an entry and through the search
 * repository.
 */
final class ClientSearchEntryWriterTest extends KernelTestCase
{
    private const string CLINIC_ID       = '01960000-0000-7000-8000-0000000003a1';
    private const string OTHER_CLINIC_ID = '01960000-0000-7000-8000-0000000003a2';
    private const string CLIENT_ID       = '01960000-0000-7000-8000-0000000003b1';

    private ClientSearchEntryWriterInterface $writer;
    private ClientSearchRepositoryInterface $search;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $writer = self::getContainer()->get(ClientSearchEntryWriterInterface::class);
        $search = self::getContainer()->get(ClientSearchRepositoryInterface::class);
        $em     = self::getContainer()->get(EntityManagerInterface::class);
        \assert($writer instanceof ClientSearchEntryWriterInterface && $search instanceof ClientSearchRepositoryInterface && $em instanceof EntityManagerInterface);

        $this->writer        = $writer;
        $this->search        = $search;
        $this->entityManager = $em;
    }

    public function testAStructureIsIndexedUnderItsLegalNameThenItsContact(): void
    {
        $this->writer->upsert($this->calanques());

        $entry = $this->reload();
        self::assertSame(self::CLIENT_ID, $entry->getId()->toRfc4122());
        self::assertSame(self::CLINIC_ID, $entry->getClinicId()->toRfc4122());
        self::assertSame(['Claire', 'Rostand', 'Élevage des Calanques'], [$entry->getFirstName(), $entry->getLastName(), $entry->getLegalName()]);
        self::assertSame('elevage des calanques claire rostand', $entry->getSearchName());
        self::assertSame('+33491223344', $entry->getSearchPhone());
        self::assertSame('contact@calanques.fr', $entry->getPrimaryEmail());
        self::assertSame('active', $entry->getStatus());

        $byName = $this->find('Élevage');
        self::assertCount(1, $byName);
        self::assertSame(self::CLIENT_ID, $byName[0]->id);
        self::assertSame('Élevage des Calanques', $byName[0]->legalName);
        self::assertSame('+33491223344', $byName[0]->searchPhone);
        self::assertSame('contact@calanques.fr', $byName[0]->primaryEmail);
        self::assertCount(1, $this->find('04 91 22 33 44'));
    }

    public function testASecondUpsertRewritesTheEntry(): void
    {
        $this->writer->upsert($this->calanques());
        $before = $this->reload()->getUpdatedAt();

        $this->writer->upsert(new ClientSearchEntryData(self::CLIENT_ID, self::CLINIC_ID, 'Hugo', 'Marchal', null, null, 'archived'));

        $entry = $this->reload();
        self::assertSame(['Hugo', 'Marchal', null], [$entry->getFirstName(), $entry->getLastName(), $entry->getLegalName()]);
        self::assertSame('hugo marchal', $entry->getSearchName());
        self::assertNull($entry->getSearchPhone());
        self::assertNull($entry->getPrimaryEmail());
        self::assertSame('archived', $entry->getStatus());
        self::assertGreaterThanOrEqual($before, $entry->getUpdatedAt());
    }

    public function testARenameReindexesTheLegalNameAndTheContact(): void
    {
        $this->writer->upsert($this->calanques());

        $this->writer->updateName(self::CLIENT_ID, self::CLINIC_ID, 'Paul', 'Girard', 'Refuge des Collines');

        $entry = $this->reload();
        self::assertSame(['Paul', 'Girard', 'Refuge des Collines'], [$entry->getFirstName(), $entry->getLastName(), $entry->getLegalName()]);
        self::assertSame('refuge des collines paul girard', $entry->getSearchName());
        self::assertCount(1, $this->find('refuge'));
        self::assertSame([], $this->find('elevage'));

        $this->writer->updateName(self::CLIENT_ID, self::CLINIC_ID, 'Paul', 'Girard', null);

        self::assertSame('paul girard', $this->reload()->getSearchName());
        self::assertNull($this->find('paul')[0]->legalName);
    }

    public function testContactMethodsAreReplaced(): void
    {
        $this->writer->upsert($this->calanques());

        $this->writer->updateContactMethods(self::CLIENT_ID, self::CLINIC_ID, '06 12 34 56 78', 'claire@calanques.fr');

        $entry = $this->reload();
        self::assertSame('+33612345678', $entry->getSearchPhone());
        self::assertSame('claire@calanques.fr', $entry->getPrimaryEmail());
        self::assertCount(1, $this->find('0612345678'));
        self::assertSame([], $this->find('0491223344'));

        $this->writer->updateContactMethods(self::CLIENT_ID, self::CLINIC_ID, null, null);

        $entry = $this->reload();
        self::assertNull($entry->getSearchPhone());
        self::assertNull($entry->getPrimaryEmail());
    }

    public function testAnArchivedClientLeavesTheSearchUntilRestored(): void
    {
        $this->writer->upsert($this->calanques());

        $this->writer->markArchived(self::CLIENT_ID, self::CLINIC_ID);

        self::assertSame('archived', $this->reload()->getStatus());
        self::assertSame([], $this->find('elevage'));

        $this->writer->markActive(self::CLIENT_ID, self::CLINIC_ID);

        self::assertSame('active', $this->reload()->getStatus());
        self::assertCount(1, $this->find('elevage'));
    }

    public function testAnotherClinicsOrAnUnknownEntryIsLeftAlone(): void
    {
        $this->writer->upsert($this->calanques());

        foreach ([[self::CLIENT_ID, self::OTHER_CLINIC_ID], [Uuid::v7()->toRfc4122(), self::CLINIC_ID]] as [$clientId, $clinicId]) {
            $this->writer->updateName($clientId, $clinicId, 'Paul', 'Girard', null);
            $this->writer->updateContactMethods($clientId, $clinicId, null, null);
            $this->writer->markArchived($clientId, $clinicId);
            $this->writer->markActive($clientId, $clinicId);
        }

        $entry = $this->reload();
        self::assertSame('elevage des calanques claire rostand', $entry->getSearchName());
        self::assertSame('+33491223344', $entry->getSearchPhone());
        self::assertSame('active', $entry->getStatus());
        self::assertSame([], $this->find('elevage', self::OTHER_CLINIC_ID));
    }

    private function calanques(): ClientSearchEntryData
    {
        return new ClientSearchEntryData(
            clientId: self::CLIENT_ID,
            clinicId: self::CLINIC_ID,
            firstName: 'Claire',
            lastName: 'Rostand',
            phone: '04 91 22 33 44',
            email: 'contact@calanques.fr',
            status: 'active',
            legalName: 'Élevage des Calanques',
        );
    }

    private function reload(): SearchEntryEntity
    {
        $this->entityManager->clear();
        $entry = $this->entityManager->find(SearchEntryEntity::class, Uuid::fromString(self::CLIENT_ID));
        self::assertInstanceOf(SearchEntryEntity::class, $entry);

        return $entry;
    }

    /**
     * @return list<ClientSearchRow>
     */
    private function find(string $term, string $clinicId = self::CLINIC_ID): array
    {
        return $this->search->findByQuery(new SearchQuery($term, $clinicId, Uuid::v7()->toRfc4122()));
    }
}
