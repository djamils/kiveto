<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

interface QueryBusInterface
{
    /**
     * Dispatch a query and return its handler result.
     */
    public function ask(object $query): mixed;
}
