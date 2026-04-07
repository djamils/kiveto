<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Application\Query\GetClientById;

use App\Client\Application\Query\GetClientById\ClientView;
use App\Client\Application\Query\GetClientById\ContactMethodDto;
use App\Client\Application\Query\GetClientById\PostalAddressDto;
use PHPUnit\Framework\TestCase;

final class ClientViewTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $contactMethod = new ContactMethodDto('phone', 'mobile', '+33612345678', true);
        $postalAddress = new PostalAddressDto('123 Main St', 'Paris', 'FR', null, '75001', null);

        $view = new ClientView(
            id: '01234567-89ab-cdef-0123-456789abcdef',
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            firstName: 'John',
            lastName: 'Doe',
            status: 'active',
            contactMethods: [$contactMethod],
            postalAddress: $postalAddress,
            createdAt: '2024-01-01T10:00:00+00:00',
            updatedAt: '2024-01-01T10:00:00+00:00'
        );

        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $view->id);
        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $view->clinicId);
        self::assertSame('John', $view->firstName);
        self::assertSame('Doe', $view->lastName);
        self::assertSame('active', $view->status);
        self::assertCount(1, $view->contactMethods);
        self::assertSame($contactMethod, $view->contactMethods[0]);
        self::assertSame($postalAddress, $view->postalAddress);
        self::assertSame('2024-01-01T10:00:00+00:00', $view->createdAt);
        self::assertSame('2024-01-01T10:00:00+00:00', $view->updatedAt);
    }

    public function testFullNameReturnsFormattedName(): void
    {
        $view = new ClientView(
            id: '01234567-89ab-cdef-0123-456789abcdef',
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            firstName: 'John',
            lastName: 'Doe',
            status: 'active',
            contactMethods: [],
            postalAddress: null,
            createdAt: '2024-01-01T10:00:00+00:00',
            updatedAt: '2024-01-01T10:00:00+00:00'
        );

        self::assertSame('John Doe', $view->fullName());
    }

    public function testFullNameTrimsExtraSpaces(): void
    {
        $view = new ClientView(
            id: '01234567-89ab-cdef-0123-456789abcdef',
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            firstName: '  John  ',
            lastName: '  Doe  ',
            status: 'active',
            contactMethods: [],
            postalAddress: null,
            createdAt: '2024-01-01T10:00:00+00:00',
            updatedAt: '2024-01-01T10:00:00+00:00'
        );

        self::assertSame('John     Doe', $view->fullName());
    }
}
