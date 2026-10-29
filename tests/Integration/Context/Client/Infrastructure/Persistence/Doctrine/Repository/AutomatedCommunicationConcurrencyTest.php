<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Domain\ClientCommunication;
use App\Context\Client\Domain\ValueObject\ClientCommunicationId;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Domain\ValueObject\DeliveryStatus;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Mapper\ClientCommunicationMapper;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Repository\DoctrineClientCommunicationRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Two consumers of the same message's statuses, on two real connections
 * (outside the test transaction): the second insert waits on the unique
 * message id instead of failing, then finds the first one's entry with a
 * locking read and keeps the newest status.
 */
final class AutomatedCommunicationConcurrencyTest extends KernelTestCase
{
    private const string CLINIC  = '01960000-0000-7000-8000-00000000c0c1';
    private const string CLIENT  = '01960000-0000-7000-8000-00000000c1c1';
    private const string MESSAGE = '01960000-0000-7000-8000-00000000c3c1';

    private Connection $first;
    private Connection $second;

    protected function setUp(): void
    {
        $default = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($default instanceof Connection);

        $this->first  = DriverManager::getConnection($default->getParams());
        $this->second = DriverManager::getConnection($default->getParams());
        $this->second->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        foreach ([$this->first, $this->second] as $connection) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }

        $this->cleanUp();
        $this->first->close();
        $this->second->close();

        parent::tearDown();
    }

    public function testASecondInsertWaitsForTheFirst(): void
    {
        $this->first->beginTransaction();
        self::assertTrue($this->journal($this->first)->addIfAbsent($this->entry('01960000-0000-7000-8000-00000000c2c1', DeliveryStatus::QUEUED, 1)));

        $this->second->beginTransaction();

        try {
            $this->journal($this->second)->addIfAbsent($this->entry('01960000-0000-7000-8000-00000000c2c2', DeliveryStatus::SENT, 2));
            self::fail('The second insert should wait for the first one.');
        } catch (LockWaitTimeoutException) {
            $this->addToAssertionCount(1);
        }
    }

    public function testTheSecondConsumerUpdatesTheEntryTheFirstInserted(): void
    {
        // The second consumer has read before the first commits: its snapshot predates the entry.
        $this->second->beginTransaction();
        self::assertSame(0, $this->second->fetchOne('SELECT COUNT(*) FROM client__client_communications WHERE client_id = ?', [Uuid::fromString(self::CLIENT)->toBinary()]));

        $this->first->beginTransaction();
        self::assertTrue($this->journal($this->first)->addIfAbsent($this->entry('01960000-0000-7000-8000-00000000c2c1', DeliveryStatus::QUEUED, 1)));
        $this->first->commit();

        $journal = $this->journal($this->second);
        self::assertFalse($journal->addIfAbsent($this->entry('01960000-0000-7000-8000-00000000c2c2', DeliveryStatus::SENT, 2)));

        $entry = $journal->findByNotificationMessageIdForUpdate(self::MESSAGE);
        self::assertNotNull($entry, 'a locking read sees the committed entry');
        self::assertTrue($entry->recordDeliveryStatus(DeliveryStatus::SENT, 2, new \DateTimeImmutable('2026-10-26 09:42:05')));
        $journal->save($entry);
        $this->second->commit();

        self::assertSame('SENT', $this->first->fetchOne('SELECT delivery_status FROM client__client_communications WHERE notification_message_id = ?', [Uuid::fromString(self::MESSAGE)->toBinary()]));
    }

    private function entry(string $id, DeliveryStatus $status, int $sequence): ClientCommunication
    {
        return ClientCommunication::recordAutomated(
            ClientCommunicationId::fromString($id),
            ClinicId::fromString(self::CLINIC),
            ClientId::fromString(self::CLIENT),
            ContactChannel::EMAIL,
            'Rendez-vous confirmé — Milo',
            null,
            self::MESSAGE,
            $status,
            $sequence,
            new \DateTimeImmutable('2026-10-26 09:42:00'),
        );
    }

    private function journal(Connection $connection): DoctrineClientCommunicationRepository
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        return new DoctrineClientCommunicationRepository(new EntityManager($connection, $em->getConfiguration()), new ClientCommunicationMapper());
    }

    private function cleanUp(): void
    {
        $this->first->executeStatement('DELETE FROM client__client_communications WHERE client_id = ?', [Uuid::fromString(self::CLIENT)->toBinary()]);
    }
}
