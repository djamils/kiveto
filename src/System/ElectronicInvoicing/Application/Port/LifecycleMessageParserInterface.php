<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Port;

use App\System\ElectronicInvoicing\Application\Exception\UnparsableLifecycleMessageException;

interface LifecycleMessageParserInterface
{
    /**
     * @throws UnparsableLifecycleMessageException
     *
     * @return list<ParsedLifecycleMessage>
     */
    public function parse(string $content): array;
}
