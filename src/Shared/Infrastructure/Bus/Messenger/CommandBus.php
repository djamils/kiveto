<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\ExternalApiCommandInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * A command whose transaction is chosen as a deadlock victim is run once
 * more: MySQL rolled the whole transaction back, so nothing of the first run
 * remains (InnoDB's advice for deadlocks). Only when this dispatch owns the
 * transaction — a command dispatched inside another one fails with it, and
 * the outer dispatch decides.
 */
class CommandBus implements CommandBusInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly ?ManagerRegistry $doctrine = null,
    ) {
        $this->messageBus = $messageBus;
    }

    public function dispatch(object $command, object ...$stamps): mixed
    {
        if ($command instanceof ExternalApiCommandInterface) {
            // Stamped for this bus, it would find no handler; and it must never run in a transaction.
            throw new \LogicException(\sprintf('%s calls an external service: send it through ExternalApiCommandBusInterface.', $command::class));
        }

        /** @var array<StampInterface> $stampsArray */
        $stampsArray = $stamps;
        $connection  = $this->connection();
        $ownsTx      = null !== $connection && !$connection->isTransactionActive();

        try {
            return $this->handle(Envelope::wrap($command, $stampsArray));
        } catch (\Throwable $e) {
            if (!$ownsTx || !self::isDeadlock($e)) {
                throw self::unwrapped($e);
            }

            $this->recover($connection);
        }

        try {
            return $this->handle(Envelope::wrap($command, $stampsArray));
        } catch (\Throwable $e) {
            if (self::isDeadlock($e)) {
                $this->recover($connection);
            }

            throw self::unwrapped($e);
        }
    }

    /**
     * The handler's own exception, not Messenger's wrapper.
     */
    private static function unwrapped(\Throwable $e): \Throwable
    {
        return $e instanceof HandlerFailedException ? $e->getPrevious() ?? $e : $e;
    }

    private function connection(): ?Connection
    {
        $connection = $this->doctrine?->getConnection();

        return $connection instanceof Connection ? $connection : null;
    }

    /**
     * A deadlock in a flush leaves DBAL's nesting level behind (the savepoint
     * rollback fails, MySQL has dropped it): the connection is closed, which
     * resets it. The entity manager is reset if the flush closed it, cleared
     * otherwise, so that the next run reads everything again.
     */
    private function recover(?Connection $connection): void
    {
        if (null !== $connection && $connection->isTransactionActive()) {
            $connection->close();
        }

        $manager = $this->doctrine?->getManager();

        if ($manager instanceof EntityManagerInterface && !$manager->isOpen()) {
            $this->doctrine?->resetManager();

            return;
        }

        $manager?->clear();
    }

    private static function isDeadlock(\Throwable $e): bool
    {
        for ($cause = $e; null !== $cause; $cause = $cause->getPrevious()) {
            if ($cause instanceof DeadlockException) {
                return true;
            }

            // The failure of a nested dispatch wraps the handler's exceptions
            if ($cause instanceof HandlerFailedException) {
                foreach ($cause->getWrappedExceptions() as $wrapped) {
                    if (self::isDeadlock($wrapped)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
