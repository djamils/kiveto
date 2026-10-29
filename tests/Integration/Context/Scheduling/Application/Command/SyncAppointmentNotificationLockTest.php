<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Application\Command;

use App\Context\Scheduling\Application\Command\SyncAppointmentNotification\SyncAppointmentNotification;
use App\Context\Scheduling\Application\Command\SyncAppointmentNotification\SyncAppointmentNotificationHandler;
use App\Context\Scheduling\Application\Notification\AppointmentMessageComposer;
use App\Context\Scheduling\Infrastructure\Calendar\IcsWriter;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Mapper\AppointmentMapper;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Repository\DbalAppointmentNotificationStateRepository;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Repository\DbalReminderSettingsRepository;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Repository\DoctrineAppointmentRepository;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use App\Tests\Unit\Context\Scheduling\Support\CapturingNotifications;
use App\Tests\Unit\Context\Scheduling\Support\FakeAnimals;
use App\Tests\Unit\Context\Scheduling\Support\FakeClientContacts;
use App\Tests\Unit\Context\Scheduling\Support\FakeClinicDirectory;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryAttachments;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * AC 15c: two syncs of one appointment, each on its own database connection
 * (outside the test transaction), run one after the other on the
 * notification state row lock: the second waits for the first, then finds
 * the confirmation already recorded — exactly one message is requested,
 * even when the second transaction's snapshot predates the first commit.
 */
final class SyncAppointmentNotificationLockTest extends KernelTestCase
{
    private const string APPOINTMENT = '01960000-0000-7000-8000-0000000a15c0';

    private Connection $first;
    private Connection $second;
    private CapturingNotifications $notifications;

    protected function setUp(): void
    {
        $default = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($default instanceof Connection);

        $this->first  = DriverManager::getConnection($default->getParams());
        $this->second = DriverManager::getConnection($default->getParams());
        $this->second->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
        $this->notifications = new CapturingNotifications();

        $this->cleanUp();
        $this->appointments($this->first)->save(Appointments::planned(id: self::APPOINTMENT));
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

    public function testASecondSyncWaitsForTheFirstThenFindsNothingLeftToSend(): void
    {
        $this->first->beginTransaction();
        $this->sync($this->first);
        self::assertCount(1, $this->notifications->messages);

        $this->second->beginTransaction();

        try {
            $this->sync($this->second);
            self::fail('The second sync should wait for the first one.');
        } catch (LockWaitTimeoutException) {
            $this->second->rollBack();
        }

        $this->first->commit();

        // Messenger retries the second sync: the confirmation is already recorded.
        $this->second->beginTransaction();
        $this->sync($this->second);
        $this->second->commit();

        self::assertCount(1, $this->notifications->messages);
        self::assertSame('appointment:' . self::APPOINTMENT . ':confirmation:1', $this->notifications->messages[0]->idempotencyKey);
    }

    public function testTheWaitingSyncReadsWhatTheFirstCommitted(): void
    {
        // The second transaction read something before the first committed: its snapshot predates it.
        $this->second->beginTransaction();
        self::assertSame(0, $this->statesSeenBySecond());

        $this->first->beginTransaction();
        $this->sync($this->first);
        $this->first->commit();

        $this->sync($this->second);
        $this->second->commit();

        self::assertCount(1, $this->notifications->messages);
    }

    private function sync(Connection $connection): void
    {
        $clinics = new FakeClinicDirectory(printedNames: [Appointments::ROUSSEAU => 'Dr Rousseau']);

        new SyncAppointmentNotificationHandler(
            new DbalAppointmentNotificationStateRepository($connection),
            $this->appointments($connection),
            new DbalReminderSettingsRepository($connection),
            $clinics,
            new FakeClientContacts(),
            new AppointmentMessageComposer(new FakeAnimals(), $clinics, new IcsWriter(), new InMemoryAttachments()),
            $this->notifications,
            new FrozenClock(Appointments::utc('2026-10-13 08:44')),
            new NullLogger(),
        )(new SyncAppointmentNotification(self::APPOINTMENT));
    }

    private function statesSeenBySecond(): int
    {
        $count = $this->second->fetchOne('SELECT COUNT(*) FROM scheduling__appointment_notifications WHERE appointment_id = ?', [Uuid::fromString(self::APPOINTMENT)->toBinary()]);
        \assert(is_numeric($count));

        return (int) $count;
    }

    private function appointments(Connection $connection): DoctrineAppointmentRepository
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        return new DoctrineAppointmentRepository(new EntityManager($connection, $em->getConfiguration()), new AppointmentMapper());
    }

    private function cleanUp(): void
    {
        $id = Uuid::fromString(self::APPOINTMENT)->toBinary();
        $this->first->executeStatement('DELETE FROM scheduling__appointment_notifications WHERE appointment_id = ?', [$id]);
        $this->first->executeStatement('DELETE FROM scheduling__appointments WHERE id = ?', [$id]);
    }
}
