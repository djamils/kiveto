<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

interface CommandBusInterface
{
    /**
     * Dispatch a command message.
     *
     * @param object ...$stamps Optional Messenger stamps (e.g. MessageMetadataStamp)
     */
    public function dispatch(object $command, object ...$stamps): mixed;
}
