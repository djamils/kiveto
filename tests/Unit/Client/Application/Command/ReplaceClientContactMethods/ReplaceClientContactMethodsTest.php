<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Application\Command\ReplaceClientContactMethods;

use App\Client\Application\Command\ReplaceClientContactMethods\ContactMethodDto;
use App\Client\Application\Command\ReplaceClientContactMethods\ReplaceClientContactMethods;
use PHPUnit\Framework\TestCase;

final class ReplaceClientContactMethodsTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $contactMethod = new ContactMethodDto(
            type: 'email',
            label: 'work',
            value: 'jane@example.com',
            isPrimary: true
        );

        $command = new ReplaceClientContactMethods(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            clientId: '01234567-89ab-cdef-0123-456789abcdef',
            contactMethods: [$contactMethod]
        );

        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $command->clinicId);
        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $command->clientId);
        self::assertCount(1, $command->contactMethods);
        self::assertSame($contactMethod, $command->contactMethods[0]);
    }
}
