<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;

/**
 * Consumes Doctrine transports in the test process, as the workers would,
 * until none of them has anything left.
 */
final class MessengerDrain
{
    /**
     * @param list<string> $transports in the order a worker drains them
     *
     * @return int how many messages were handled
     */
    public static function drain(ContainerInterface $container, array $transports = ['async_external', 'async_external_batch', 'async'], int $limit = 1000): int
    {
        $receivers = [];
        foreach ($transports as $name) {
            $receiver = $container->get('messenger.transport.' . $name);
            \assert($receiver instanceof ReceiverInterface);
            $receivers[$name] = $receiver;
        }

        $bus = $container->get('messenger.routable_message_bus');
        \assert($bus instanceof MessageBusInterface);

        $handled    = 0;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) use (&$handled, $limit): void {
            if ($event->isWorkerIdle() || $handled >= $limit) {
                $event->getWorker()->stop();

                return;
            }

            ++$handled;
        });

        new Worker($receivers, $bus, $dispatcher)->run(['sleep' => 0]);

        return $handled;
    }
}
