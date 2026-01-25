<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Domain\ValueObject;

use App\Client\Domain\ValueObject\ClientIdentity;
use PHPUnit\Framework\TestCase;

final class ClientIdentityTest extends TestCase
{
    public function testCreatesWithValidNames(): void
    {
        $identity = new ClientIdentity('John', 'Doe');

        self::assertSame('John', $identity->firstName);
        self::assertSame('Doe', $identity->lastName);
    }

    public function testFullName(): void
    {
        $identity = new ClientIdentity('John', 'Doe');

        self::assertSame('John Doe', $identity->fullName());
    }

    public function testFullNameTrimsResult(): void
    {
        $identity = new ClientIdentity('  John  ', '  Doe  ');

        // fullName() trims the final result
        self::assertSame('John     Doe', $identity->fullName());
    }

    public function testThrowsExceptionWhenFirstNameIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First name cannot be empty.');

        new ClientIdentity('', 'Doe');
    }

    public function testThrowsExceptionWhenFirstNameIsOnlyWhitespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First name cannot be empty.');

        new ClientIdentity('   ', 'Doe');
    }

    public function testThrowsExceptionWhenLastNameIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Last name cannot be empty.');

        new ClientIdentity('John', '');
    }

    public function testThrowsExceptionWhenLastNameIsOnlyWhitespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Last name cannot be empty.');

        new ClientIdentity('John', '   ');
    }
}
