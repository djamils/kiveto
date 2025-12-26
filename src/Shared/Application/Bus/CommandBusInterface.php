<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

interface CommandBusInterface
{
    /**
     * Dispatch a command message.
     */
    public function dispatch(object $command): mixed;
}
