<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Application\Command\CreateClient;

use App\Client\Application\Command\CreateClient\ContactMethodDto;
use App\Client\Application\Command\CreateClient\CreateClient;
use PHPUnit\Framework\TestCase;

final class CreateClientTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $contactMethod = new ContactMethodDto(
            type: 'phone',
            label: 'mobile',
            value: '+33612345678',
            isPrimary: true
        );

        $command = new CreateClient(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            firstName: 'John',
            lastName: 'Doe',
            contactMethods: [$contactMethod]
        );

        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $command->clinicId);
        self::assertSame('John', $command->firstName);
        self::assertSame('Doe', $command->lastName);
        self::assertCount(1, $command->contactMethods);
        self::assertSame($contactMethod, $command->contactMethods[0]);
    }

    public function testConstructorAcceptsEmptyContactMethods(): void
    {
        $command = new CreateClient(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            firstName: 'John',
            lastName: 'Doe',
            contactMethods: []
        );

        self::assertCount(0, $command->contactMethods);
    }
}
