<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

interface QueryBusInterface
{
    /**
     * Dispatch a query and return its handler result.
     *
     * @param object ...$stamps Optional Messenger stamps (e.g. MessageMetadataStamp)
     */
    public function ask(object $query, object ...$stamps): mixed;
}
