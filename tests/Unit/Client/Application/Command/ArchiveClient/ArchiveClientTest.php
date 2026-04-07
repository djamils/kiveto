<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Application\Command\ArchiveClient;

use App\Client\Application\Command\ArchiveClient\ArchiveClient;
use PHPUnit\Framework\TestCase;

final class ArchiveClientTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $command = new ArchiveClient(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            clientId: '01234567-89ab-cdef-0123-456789abcdef'
        );

        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $command->clinicId);
        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $command->clientId);
    }
}
