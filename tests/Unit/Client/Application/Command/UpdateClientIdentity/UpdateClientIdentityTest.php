<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Application\Command\UpdateClientIdentity;

use App\Client\Application\Command\UpdateClientIdentity\UpdateClientIdentity;
use PHPUnit\Framework\TestCase;

final class UpdateClientIdentityTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $command = new UpdateClientIdentity(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            clientId: '01234567-89ab-cdef-0123-456789abcdef',
            firstName: 'Jane',
            lastName: 'Smith'
        );

        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $command->clinicId);
        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $command->clientId);
        self::assertSame('Jane', $command->firstName);
        self::assertSame('Smith', $command->lastName);
    }
}
