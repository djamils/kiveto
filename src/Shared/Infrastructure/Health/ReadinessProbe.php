<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

/**
 * Whether this instance can serve: the database answers and every queue the
 * workers consume is readable. One `SELECT 1`, then one count per queue (an
 * indexed query on `shared__messenger_messages`). The queues live in that
 * database: when it does not answer, they are not tried, so that a probe
 * waits for one connection timeout, not one per queue. A failure is logged
 * with its cause; the caller only learns which check failed.
 */
final readonly class ReadinessProbe
{
    /**
     * @param list<string> $transports the consumed transports, by name
     */
    public function __construct(
        private Connection $connection,
        #[AutowireLocator('messenger.receiver', indexAttribute: 'alias')]
        private ContainerInterface $receivers,
        #[Autowire(param: 'health.messenger_transports')]
        private array $transports,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{database: bool, messenger: bool}
     */
    public function check(): array
    {
        $database = $this->passes('database', fn (): mixed => $this->connection->executeQuery('SELECT 1')->fetchOne());

        if (!$database) {
            return ['database' => false, 'messenger' => false];
        }

        $messenger = true;

        foreach ($this->transports as $name) {
            $messenger = $this->passes('messenger.' . $name, fn (): int => $this->countable($name)->getMessageCount()) && $messenger;
        }

        return ['database' => $database, 'messenger' => $messenger];
    }

    private function countable(string $name): MessageCountAwareInterface
    {
        $receiver = $this->receivers->get($name);

        if (!$receiver instanceof MessageCountAwareInterface) {
            throw new \LogicException(\sprintf('The transport "%s" cannot be probed: it does not count its messages.', $name));
        }

        return $receiver;
    }

    private function passes(string $check, \Closure $probe): bool
    {
        try {
            $probe();

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Readiness check failed.', ['check' => $check, 'exception' => $e]);

            return false;
        }
    }
}
