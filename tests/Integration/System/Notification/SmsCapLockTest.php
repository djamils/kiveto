<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Notification;

use App\System\Notification\Infrastructure\Persistence\Doctrine\Mapper\OutboundMessageMapper;
use App\System\Notification\Infrastructure\Persistence\Doctrine\Repository\DbalClinicMessagingProfileRepository;
use App\System\Notification\Infrastructure\Persistence\Doctrine\Repository\DoctrineOutboundMessageRepository;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\Notification\Support\InMemoryProfiles;
use App\Tests\Unit\System\Notification\Support\Messages;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The SMS cap check of a clinic is serialised on its messaging profile row
 * (`SELECT … FOR UPDATE`, held until COMMIT), proven with two real database
 * connections (outside the test transaction): a second request waits for the
 * first, then sees the SMS the first one queued — even though its own
 * REPEATABLE READ snapshot was taken before that commit.
 */
final class SmsCapLockTest extends KernelTestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000cca1';

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
        $this->profiles($this->first)->save(InMemoryProfiles::montmartre(self::CLINIC));
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

    public function testASecondCapCheckWaitsForTheFirst(): void
    {
        $this->first->beginTransaction();
        self::assertNotNull($this->profiles($this->first)->findForUpdate(self::CLINIC));

        $this->second->beginTransaction();

        try {
            $this->profiles($this->second)->findForUpdate(self::CLINIC);
            self::fail('The second cap check should wait for the first one.');
        } catch (LockWaitTimeoutException) {
            $this->addToAssertionCount(1);
        }
    }

    public function testTheSecondCapCheckSeesWhatTheFirstQueued(): void
    {
        // The second request has read something before the first commits: its snapshot predates it.
        $this->second->beginTransaction();
        self::assertSame(0, $this->rowsSeenBySecond());

        $this->first->beginTransaction();
        self::assertNotNull($this->profiles($this->first)->findForUpdate(self::CLINIC));
        $this->messages($this->first)->save(Messages::sms(id: '01960000-0000-7000-8000-0000000cca01', key: 'cap-lock:1', clinicId: self::CLINIC, segments: 7));
        $this->first->commit();

        self::assertNotNull($this->profiles($this->second)->findForUpdate(self::CLINIC));
        self::assertSame(7, $this->messages($this->second)->sumQueuedSegments(self::CLINIC));
        self::assertSame(
            0,
            $this->rowsSeenBySecond(),
            'A plain read would still see the old snapshot: the sums must be locking reads.',
        );
        $this->second->commit();
    }

    private function rowsSeenBySecond(): int
    {
        $count = $this->second->fetchOne('SELECT COUNT(*) FROM notification__outbound_messages WHERE clinic_id = ?', [Uuid::fromString(self::CLINIC)->toBinary()]);
        \assert(is_numeric($count));

        return (int) $count;
    }

    private function profiles(Connection $connection): DbalClinicMessagingProfileRepository
    {
        return new DbalClinicMessagingProfileRepository($connection, new FrozenClock(new \DateTimeImmutable('2026-10-26 10:00:00')));
    }

    private function messages(Connection $connection): DoctrineOutboundMessageRepository
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        return new DoctrineOutboundMessageRepository(new EntityManager($connection, $em->getConfiguration()), new OutboundMessageMapper());
    }

    private function cleanUp(): void
    {
        $clinic = Uuid::fromString(self::CLINIC)->toBinary();
        $this->first->executeStatement('DELETE FROM notification__outbound_messages WHERE clinic_id = ?', [$clinic]);
        $this->first->executeStatement('DELETE FROM notification__clinic_messaging_profiles WHERE clinic_id = ?', [$clinic]);
    }
}
