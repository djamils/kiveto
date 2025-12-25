<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

interface EventBusInterface
{
    /**
     * Publish one or many events.
     */
    public function publish(object ...$events): void;
}

