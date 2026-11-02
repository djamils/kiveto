<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Infrastructure\Bus\Messenger\CommandBus;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * S4A-05: a command chosen as a deadlock victim is run once more by the bus
 * that owns its transaction (MySQL rolled all of it back); the entity
 * manager is cleared — or reset, if the failed flush closed it — and a
 * connection left inside a dead transaction is closed first.
 */
#[AllowMockObjectsWithoutExpectations]
final class CommandBusDeadlockRetryTest extends TestCase
{
    public function testADeadlockedCommandIsRunOnceMore(): void
    {
        $bus = $this->bus([self::failure(self::deadlock()), 'done']);
        $em  = $this->createMock(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);
        $em->expects(self::once())->method('clear');

        self::assertSame('done', new CommandBus($bus, $this->registry($this->connection([false, false]), $em))->dispatch(new \stdClass()));
        self::assertCount(2, $bus);
    }

    public function testAContendedSlotCausedByADeadlockIsRunOnceMoreToo(): void
    {
        // A domain exception carrying the deadlock, among the handler's failures
        $contended = new \DomainException('contended', 0, self::deadlock());
        $bus       = $this->bus([new HandlerFailedException(new Envelope(new \stdClass()), [new \RuntimeException('other'), $contended]), 'done']);

        self::assertSame('done', new CommandBus($bus, $this->registry($this->connection([false, false]), $this->openManager()))->dispatch(new \stdClass()));
    }

    public function testAFlushThatClosedTheManagerAndLeftTheTransactionOpenIsRecovered(): void
    {
        $bus        = $this->bus([self::failure(new \RuntimeException('SAVEPOINT DOCTRINE_2 does not exist', 0, self::deadlock())), 'done']);
        $connection = $this->connection([false, true]);
        $connection->expects(self::once())->method('close');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(false);
        $em->expects(self::never())->method('clear');
        $registry = $this->registry($connection, $em);
        $registry->expects(self::once())->method('resetManager');

        self::assertSame('done', new CommandBus($bus, $registry)->dispatch(new \stdClass()));
    }

    public function testASecondDeadlockReachesTheCallerOnACleanConnection(): void
    {
        $bus        = $this->bus([self::failure(self::deadlock()), self::failure(self::deadlock())]);
        $connection = $this->connection([false, false, true]);
        $connection->expects(self::once())->method('close');
        $em = $this->openManager();
        $em->expects(self::exactly(2))->method('clear');

        try {
            new CommandBus($bus, $this->registry($connection, $em))->dispatch(new \stdClass());
            self::fail('The second deadlock should reach the caller.');
        } catch (DeadlockException) {
        }

        self::assertCount(2, $bus);
    }

    public function testACommandDispatchedInsideAnotherTransactionFailsWithIt(): void
    {
        $bus = $this->bus([self::failure(self::deadlock())]);

        $this->expectException(DeadlockException::class);

        try {
            new CommandBus($bus, $this->registry($this->connection([true]), $this->openManager()))->dispatch(new \stdClass());
        } finally {
            self::assertCount(1, $bus);
        }
    }

    public function testAnyOtherFailureIsNotRunAgain(): void
    {
        $bus = $this->bus([self::failure(new \DomainException('refused'))]);

        $this->expectExceptionMessage('refused');

        try {
            new CommandBus($bus, $this->registry($this->connection([false]), $this->openManager()))->dispatch(new \stdClass());
        } finally {
            self::assertCount(1, $bus);
        }
    }

    public function testWithoutDoctrineNothingIsRunAgain(): void
    {
        $bus = $this->bus([self::failure(self::deadlock())]);

        $this->expectException(DeadlockException::class);

        new CommandBus($bus)->dispatch(new \stdClass());
    }

    /**
     * @param list<\Throwable|string> $outcomes one per dispatch: thrown, or the handled result
     */
    private function bus(array $outcomes): MessageBusInterface&\Countable
    {
        return new class($outcomes) implements MessageBusInterface, \Countable {
            public int $dispatched = 0;

            /**
             * @param list<\Throwable|string> $outcomes
             */
            public function __construct(private array $outcomes)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                ++$this->dispatched;
                $outcome = array_shift($this->outcomes);

                if ($outcome instanceof \Throwable) {
                    throw $outcome;
                }

                return Envelope::wrap($message, $stamps)->with(new HandledStamp($outcome, 'handler'));
            }

            /**
             * @return int<0, max> how many times a command was dispatched
             */
            public function count(): int
            {
                return max(0, $this->dispatched);
            }
        };
    }

    /**
     * @param list<bool> $active successive answers of isTransactionActive()
     */
    private function connection(array $active): Connection&\PHPUnit\Framework\MockObject\MockObject
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturnOnConsecutiveCalls(...$active);

        return $connection;
    }

    private function registry(Connection $connection, EntityManagerInterface $em): ManagerRegistry&\PHPUnit\Framework\MockObject\MockObject
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->willReturn($connection);
        $registry->method('getManager')->willReturn($em);

        return $registry;
    }

    private function openManager(): EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);

        return $em;
    }

    private static function failure(\Throwable $e): HandlerFailedException
    {
        return new HandlerFailedException(new Envelope(new \stdClass()), [$e]);
    }

    private static function deadlock(): DeadlockException
    {
        return new DeadlockException(new class('Deadlock found when trying to get lock') extends AbstractException {
        }, null);
    }
}
