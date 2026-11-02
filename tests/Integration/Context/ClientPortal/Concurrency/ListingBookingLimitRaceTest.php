<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Concurrency;

use App\Context\ClientPortal\Application\Command\RequestListingBooking\RequestListingBooking;
use App\Context\ClientPortal\Application\Command\RequestListingBooking\RequestListingBookingHandler;
use App\Context\ClientPortal\Domain\Exception\BookingRequestLimitException;
use App\Context\ClientPortal\Domain\Exception\ListingBookingRateLimitedException;
use App\Context\ClientPortal\Infrastructure\Lock\DbalPortalLock;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalBookingRequestRepository;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Tests\Unit\Context\ClientPortal\Support\BookingRequestWorld as W;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * S7-02 (D14), outside the test transaction, on two real connections: two
 * new-client requests of the same account, with no pending one yet. The
 * second transaction stops right after its limit check (the daily quota
 * refuses it, its transaction stays open with its locks); the first one
 * then waits for it. Serialised on the account lock, the second one goes on
 * and records its request (the empty `FOR UPDATE` count alone left a gap
 * lock to each: their inserts waited for each other, a deadlock), and the
 * first one, retried, meets the one-per-clinic limit.
 */
final class ListingBookingLimitRaceTest extends KernelTestCase
{
    private const string FIRST       = '01960000-0000-7000-8000-0000000e7b01';
    private const string SECOND      = '01960000-0000-7000-8000-0000000e7b02';
    private const string FIRST_SLOT  = '01960000-0000-7000-8000-0000000a7b01';
    private const string SECOND_SLOT = '01960000-0000-7000-8000-0000000a7b02';

    private Connection $first;
    private Connection $second;

    protected function setUp(): void
    {
        $default = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($default instanceof Connection);

        $this->first  = DriverManager::getConnection($default->getParams());
        $this->second = DriverManager::getConnection($default->getParams());

        foreach ([$this->first, $this->second] as $connection) {
            $connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
        }

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        foreach ([$this->first, $this->second] as $connection) {
            while ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }

        $this->cleanUp();
        $this->first->close();
        $this->second->close();

        parent::tearDown();
    }

    public function testTwoConcurrentRequestsAtTheClinicKeepOnePending(): void
    {
        $this->first->beginTransaction();
        $this->second->beginTransaction();

        $paused                  = new W();
        $paused->quotaRetryAfter = 60;

        try {
            $this->request($paused, $this->second, self::SECOND, self::SECOND_SLOT);
            self::fail('The daily quota stops the second request after its limit check.');
        } catch (ListingBookingRateLimitedException) {
            self::assertTrue($this->second->isTransactionActive());
        }

        try {
            $this->request(new W(), $this->first, self::FIRST, self::FIRST_SLOT);
            self::fail('The first request should wait for the second transaction.');
        } catch (LockWaitTimeoutException) {
            self::assertTrue($this->first->isTransactionActive());
        }

        $paused->quotaRetryAfter = null;

        try {
            self::assertSame(self::SECOND, $this->request($paused, $this->second, self::SECOND, self::SECOND_SLOT));
        } catch (LockWaitTimeoutException) {
            self::fail('The second request waited for the gap lock of the first one: they deadlock.');
        }

        $this->second->commit();

        try {
            $this->request(new W(), $this->first, self::FIRST, self::FIRST_SLOT);
            self::fail('One pending request per clinic.');
        } catch (BookingRequestLimitException $limit) {
            self::assertSame(BookingRequestLimitException::CLINIC, $limit->limit);
        }

        $this->first->commit();

        self::assertSame([self::SECOND], $this->committedIds());
    }

    private function request(W $world, Connection $connection, string $id, string $slot): string
    {
        $world->booking->newClientAppointment = $slot;

        $handler = new RequestListingBookingHandler(
            $world,
            $world->links,
            new DbalBookingRequestRepository($connection),
            $world,
            $world->booking,
            $world,
            $world->profiles,
            $world,
            new class($id) implements UuidGeneratorInterface {
                public function __construct(private readonly string $id)
                {
                }

                public function generate(): string
                {
                    return $this->id;
                }
            },
            $world->clock,
            new DbalPortalLock($connection),
        );

        return $handler(new RequestListingBooking(W::USER, W::LISTING, 'Pixel', 'cat', W::MOTIF, W::VET, W::utc('2026-11-05 13:30:00')));
    }

    /**
     * @return list<string>
     */
    private function committedIds(): array
    {
        $fresh = DriverManager::getConnection($this->first->getParams());

        try {
            return array_map(
                static fn (mixed $id): string => Uuid::fromBinary(\is_string($id) ? $id : '')->toRfc4122(),
                $fresh->fetchFirstColumn('SELECT id FROM ' . DbalBookingRequestRepository::TABLE . ' WHERE user_id = ?', [Uuid::fromString(W::USER)->toBinary()]),
            );
        } finally {
            $fresh->close();
        }
    }

    private function cleanUp(): void
    {
        $this->first->executeStatement('DELETE FROM ' . DbalBookingRequestRepository::TABLE . ' WHERE user_id = ?', [Uuid::fromString(W::USER)->toBinary()]);
        $this->first->executeStatement('DELETE FROM ' . DbalPortalLock::TABLE . ' WHERE lock_key = ?', ['account:' . W::USER]);
    }
}
