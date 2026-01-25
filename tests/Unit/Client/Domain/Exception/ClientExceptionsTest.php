<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Domain\Exception;

use App\Client\Domain\Exception\ClientAlreadyArchivedException;
use App\Client\Domain\Exception\ClientArchivedCannotBeModifiedException;
use App\Client\Domain\Exception\ClientClinicMismatchException;
use App\Client\Domain\Exception\ClientMustHaveAtLeastOneContactMethodException;
use App\Client\Domain\Exception\ClientNotFoundException;
use App\Client\Domain\Exception\DuplicateContactMethodException;
use App\Client\Domain\Exception\PrimaryContactMethodConflictException;
use PHPUnit\Framework\TestCase;

final class ClientExceptionsTest extends TestCase
{
    public function testClientNotFoundException(): void
    {
        $clientId  = '01234567-89ab-cdef-0123-456789abcdef';
        $exception = ClientNotFoundException::forId($clientId);

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame('Client with ID "01234567-89ab-cdef-0123-456789abcdef" not found.', $exception->getMessage());
    }

    public function testClientAlreadyArchivedException(): void
    {
        $clientId  = '01234567-89ab-cdef-0123-456789abcdef';
        $exception = ClientAlreadyArchivedException::forId($clientId);

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame('Client "01234567-89ab-cdef-0123-456789abcdef" is already archived.', $exception->getMessage());
    }

    public function testClientArchivedCannotBeModifiedException(): void
    {
        $clientId  = '01234567-89ab-cdef-0123-456789abcdef';
        $exception = ClientArchivedCannotBeModifiedException::forId($clientId);

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame('Archived client "01234567-89ab-cdef-0123-456789abcdef" cannot be modified.', $exception->getMessage());
    }

    public function testClientClinicMismatchException(): void
    {
        $clientId         = '01234567-89ab-cdef-0123-456789abcdef';
        $expectedClinicId = '12345678-9abc-def0-1234-56789abcdef0';
        $exception        = ClientClinicMismatchException::create($clientId, $expectedClinicId);

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame(
            'Client "01234567-89ab-cdef-0123-456789abcdef" does not belong to clinic "12345678-9abc-def0-1234-56789abcdef0".',
            $exception->getMessage()
        );
    }

    public function testClientMustHaveAtLeastOneContactMethodException(): void
    {
        $exception = ClientMustHaveAtLeastOneContactMethodException::create();

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame('Client must have at least one contact method (phone or email).', $exception->getMessage());
    }

    public function testDuplicateContactMethodException(): void
    {
        $exception = DuplicateContactMethodException::create('phone', '+33612345678');

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame('Duplicate contact method: phone (+33612345678) already exists.', $exception->getMessage());
    }

    public function testPrimaryContactMethodConflictExceptionForPhones(): void
    {
        $exception = PrimaryContactMethodConflictException::forPhones();

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame('Only one primary phone contact method is allowed.', $exception->getMessage());
    }

    public function testPrimaryContactMethodConflictExceptionForEmails(): void
    {
        $exception = PrimaryContactMethodConflictException::forEmails();

        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertSame('Only one primary email contact method is allowed.', $exception->getMessage());
    }
}
