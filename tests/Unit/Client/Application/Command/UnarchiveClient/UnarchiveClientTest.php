<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Application\Command\UnarchiveClient;

use App\Client\Application\Command\UnarchiveClient\UnarchiveClient;
use PHPUnit\Framework\TestCase;

final class UnarchiveClientTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $command = new UnarchiveClient(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            clientId: '01234567-89ab-cdef-0123-456789abcdef'
        );

        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $command->clinicId);
        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $command->clientId);
    }
}
