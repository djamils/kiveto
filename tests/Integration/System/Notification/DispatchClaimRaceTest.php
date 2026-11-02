<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Notification;

use App\System\Notification\Application\Command\MaskPortalRecipient\MaskPortalRecipient;
use App\System\Notification\Application\Command\MaskPortalRecipient\MaskPortalRecipientHandler;
use App\System\Notification\Application\Command\RecordDispatchOutcome\RecordDispatchOutcome;
use App\System\Notification\Application\Command\RecordDispatchOutcome\RecordDispatchOutcomeHandler;
use App\System\Notification\Application\Service\DeliveryEventReplayer;
use App\System\Notification\Domain\Message\DeliveryStatus;
use App\System\Notification\Domain\Message\MessagePurpose;
use App\System\Notification\Domain\Message\OutboundMessageId;
use App\System\Notification\Infrastructure\Persistence\Doctrine\Mapper\OutboundMessageMapper;
use App\System\Notification\Infrastructure\Persistence\Doctrine\Repository\DoctrineOutboundMessageRepository;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\Notification\Support\CapturingBuses;
use App\Tests\Unit\System\Notification\Support\CapturingMessageBus;
use App\Tests\Unit\System\Notification\Support\InMemoryUnmatchedEvents;
use App\Tests\Unit\System\Notification\Support\Messages;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * FU-02 / FU-03, outside the test transaction, on two real connections: the
 * account deletion (`$deletion`, one transaction) and the dispatch worker
 * (`$worker`, autocommit, its claim in a short transaction of its own).
 * Whichever takes the message's row lock first wins: a cancel committed
 * first leaves nothing to send; a claim committed first turns the cancel
 * into a mask, and the provider id is still recorded. The deletion never
 * fails on a version the worker bumped (no OptimisticLockException).
 */
final class DispatchClaimRaceTest extends KernelTestCase
{
    private const string ACCOUNT = '01960000-0000-7000-8000-0000000d7a01';
    private const string MESSAGE = '01960000-0000-7000-8000-0000000d7e01';

    private Connection $deletion;
    private Connection $worker;

    protected function setUp(): void
    {
        $default = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($default instanceof Connection);

        $this->deletion = DriverManager::getConnection($default->getParams());
        $this->worker   = DriverManager::getConnection($default->getParams());
        $this->worker->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');

        $this->cleanUp();
        $this->repository($this->deletion)->save(Messages::sms(id: self::MESSAGE, key: 'claim-race:1', purpose: MessagePurpose::PORTAL_OTP, accountId: self::ACCOUNT));
    }

    protected function tearDown(): void
    {
        foreach ([$this->deletion, $this->worker] as $connection) {
            while ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }

        $this->cleanUp();
        $this->deletion->close();
        $this->worker->close();

        parent::tearDown();
    }

    public function testACancelCommittedFirstLeavesNothingToSend(): void
    {
        $this->deletion->beginTransaction();
        self::assertSame(1, $this->mask($this->repository($this->deletion)));

        try {
            $this->claim($this->repository($this->worker));
            self::fail('The claim should wait for the deletion to commit.');
        } catch (LockWaitTimeoutException) {
            $this->addToAssertionCount(1);
        }

        $this->deletion->commit();

        self::assertNull($this->claim($this->repository($this->worker)), 'cancelled: nothing is handed over');
        self::assertSame(['CANCELLED', null], $this->row());
    }

    public function testAClaimCommittedFirstIsMaskedAndItsOutcomeRecorded(): void
    {
        $worker = $this->repository($this->worker);
        self::assertNotNull($this->claim($worker), 'claimed: the dispatch hands it over');

        $this->deletion->beginTransaction();
        self::assertSame(1, $this->mask($this->repository($this->deletion)));
        $this->deletion->commit();
        self::assertSame(['QUEUED', null], $this->row(), 'masked, not cancelled');

        $this->worker->beginTransaction();
        $this->record($worker);
        $this->worker->commit();

        self::assertSame(['SENT', 'brevo-sms-race'], [$this->row()[0], $this->externalId()]);
    }

    /**
     * FU-03: the deletion has read the message before the worker claimed it
     * (stale entity, stale version in its unit of work); the erasure still
     * commits, on the latest row.
     */
    public function testADeletionThatReadTheMessageBeforeTheClaimStillCommits(): void
    {
        $deletion = $this->repository($this->deletion);
        $this->deletion->beginTransaction();
        self::assertSame(DeliveryStatus::QUEUED, $deletion->get(OutboundMessageId::fromString(self::MESSAGE))->status());

        self::assertNotNull($this->claim($this->repository($this->worker)));

        self::assertSame(1, $this->mask($deletion));
        $this->deletion->commit();

        self::assertSame(['QUEUED', null], $this->row(), 'claimed meanwhile: masked, not cancelled');
    }

    private function mask(DoctrineOutboundMessageRepository $messages): int
    {
        $handler = new MaskPortalRecipientHandler($messages, new CapturingMessageBus(), new FrozenClock(new \DateTimeImmutable('2026-10-21 09:31:00')), new CapturingBuses()->publisher());

        return $handler(new MaskPortalRecipient(self::ACCOUNT));
    }

    private function claim(DoctrineOutboundMessageRepository $messages): ?object
    {
        return $messages->claimForDispatch(OutboundMessageId::fromString(self::MESSAGE), new \DateTimeImmutable('2026-10-21 09:30:30'));
    }

    private function record(DoctrineOutboundMessageRepository $messages): void
    {
        $unmatched = new InMemoryUnmatchedEvents($messages);
        $publisher = new CapturingBuses()->publisher();

        new RecordDispatchOutcomeHandler($messages, $unmatched, new DeliveryEventReplayer($messages, $unmatched, $publisher), $publisher)(
            new RecordDispatchOutcome(self::MESSAGE, 'brevo-sms-race', null, '2026-10-21T09:31:30+00:00'),
        );
    }

    /**
     * @return array{string, ?string} status and recipient, as committed
     */
    private function row(): array
    {
        $row = $this->fresh('SELECT status, recipient_address FROM notification__outbound_messages WHERE id = ?');
        \assert(\is_array($row) && \is_string($row['status']) && (null === $row['recipient_address'] || \is_string($row['recipient_address'])));

        return [$row['status'], $row['recipient_address']];
    }

    private function externalId(): mixed
    {
        $row = $this->fresh('SELECT external_message_id FROM notification__outbound_messages WHERE id = ?');
        \assert(\is_array($row));

        return $row['external_message_id'];
    }

    private function fresh(string $sql): mixed
    {
        $fresh = DriverManager::getConnection($this->deletion->getParams());

        try {
            return $fresh->fetchAssociative($sql, [Uuid::fromString(self::MESSAGE)->toBinary()]);
        } finally {
            $fresh->close();
        }
    }

    private function repository(Connection $connection): DoctrineOutboundMessageRepository
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        return new DoctrineOutboundMessageRepository(new EntityManager($connection, $em->getConfiguration()), new OutboundMessageMapper());
    }

    private function cleanUp(): void
    {
        $this->deletion->executeStatement('DELETE FROM notification__outbound_messages WHERE id = ?', [Uuid::fromString(self::MESSAGE)->toBinary()]);
    }
}
