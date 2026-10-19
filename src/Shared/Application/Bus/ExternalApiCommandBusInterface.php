<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Queues commands that call external services; nothing is handled in the request.
 *
 * Interactive commands go to `async_external`; batch senders override it with a
 * TransportNamesStamp(['async_external_batch']), drained after the interactive queue.
 */
interface ExternalApiCommandBusInterface
{
    /**
     * @param object ...$stamps Optional Messenger stamps (e.g. TransportNamesStamp)
     */
    public function send(ExternalApiCommandInterface $command, object ...$stamps): void;
}
