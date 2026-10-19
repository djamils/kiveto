<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * A command whose handler calls an external service.
 *
 * Never a network call inside a database transaction: such a command is sent
 * through ExternalApiCommandBusInterface to the `async_external` transport
 * (written in the caller's transaction, implicit outbox), then handled on
 * `messenger.bus.external_api` (no transaction) by the external worker. Its
 * handler records the outcome through a short transactional command.
 */
interface ExternalApiCommandInterface extends CommandInterface
{
}
