<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Support;

use App\System\ElectronicInvoicing\Application\Exception\UnparsableLifecycleMessageException;
use App\System\ElectronicInvoicing\Application\Port\LifecycleMessageParserInterface;
use App\System\ElectronicInvoicing\Application\Port\ParsedLifecycleMessage;

/**
 * Reads « status|number|seller » contents; anything else is unparsable.
 */
final class FakeParser implements LifecycleMessageParserInterface
{
    public function parse(string $content): array
    {
        $parts = explode('|', $content);

        if (3 !== \count($parts)) {
            throw new UnparsableLifecycleMessageException('not a test message');
        }

        return [new ParsedLifecycleMessage((int) $parts[0], null, null, $parts[1], null, '380', $parts[2], new \DateTimeImmutable('2026-10-21 10:30'))];
    }
}
