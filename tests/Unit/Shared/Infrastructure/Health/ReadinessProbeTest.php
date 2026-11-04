<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Health;

use App\Shared\Infrastructure\Health\ReadinessProbe;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class ReadinessProbeTest extends TestCase
{
    private BufferingLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new BufferingLogger();
    }

    public function testEveryCheckPasses(): void
    {
        $probe = $this->probe($this->database(), ['async' => self::queue(), 'failed' => self::queue()]);

        self::assertSame(['database' => true, 'messenger' => true], $probe->check());
        self::assertSame([], $this->logger->cleanLogs());
    }

    public function testAnUnreachableDatabaseFailsEveryCheckWithoutTryingTheQueues(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willThrowException(new \RuntimeException('Connection refused'));
        $queue = self::queue();

        self::assertSame(['database' => false, 'messenger' => false], $this->probe($connection, ['async' => $queue])->check());
        self::assertSame(0, $queue->counts, 'the queues live in the unreachable database');
        self::assertSame([['error', 'Readiness check failed.', 'database', 'Connection refused']], $this->logs());
    }

    public function testAnUnreadableQueueFailsTheMessengerCheckAndTheOthersAreStillRead(): void
    {
        $failed = self::queue();
        $checks = $this->probe($this->database(), ['async' => self::queue(new \RuntimeException('Table not found')), 'failed' => $failed])->check();

        self::assertSame(['database' => true, 'messenger' => false], $checks);
        self::assertSame(1, $failed->counts, 'the next queue is still read');
        self::assertSame([['error', 'Readiness check failed.', 'messenger.async', 'Table not found']], $this->logs());
    }

    public function testATransportThatDoesNotCountItsMessagesIsAConfigurationError(): void
    {
        $checks = $this->probe($this->database(), ['sync' => new SyncTransport($this->createStub(MessageBusInterface::class))])->check();

        self::assertFalse($checks['messenger']);
        self::assertSame([['error', 'Readiness check failed.', 'messenger.sync', 'The transport "sync" cannot be probed: it does not count its messages.']], $this->logs());
    }

    /**
     * @param array<string, TransportInterface> $receivers every one probed
     */
    private function probe(Connection $connection, array $receivers): ReadinessProbe
    {
        $factories = array_map(static fn (TransportInterface $receiver): \Closure => static fn (): TransportInterface => $receiver, $receivers);

        return new ReadinessProbe($connection, new ServiceLocator($factories), array_keys($receivers), $this->logger);
    }

    private function database(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    /** A queue counting its messages, or failing to. */
    private static function queue(?\Throwable $failure = null): CountingQueue
    {
        return new CountingQueue($failure);
    }

    /** @return list<array{string, string, string, string}> level, message, check, cause */
    private function logs(): array
    {
        $logs = [];
        foreach ($this->logger->cleanLogs() as $log) {
            \assert(\is_array($log) && \is_string($log[0]) && \is_string($log[1]) && \is_array($log[2]));
            $check     = $log[2]['check'] ?? null;
            $exception = $log[2]['exception'] ?? null;
            \assert(\is_string($check) && $exception instanceof \Throwable);
            $logs[] = [$log[0], $log[1], $check, $exception->getMessage()];
        }

        return $logs;
    }
}
