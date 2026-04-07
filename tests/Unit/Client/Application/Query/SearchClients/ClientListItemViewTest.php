<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Application\Query\SearchClients;

use App\Client\Application\Query\SearchClients\ClientListItemView;
use PHPUnit\Framework\TestCase;

final class ClientListItemViewTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $view = new ClientListItemView(
            id: '01234567-89ab-cdef-0123-456789abcdef',
            firstName: 'John',
            lastName: 'Doe',
            status: 'active',
            primaryPhone: '+33612345678',
            primaryEmail: 'john@example.com',
            createdAt: '2024-01-01T10:00:00+00:00'
        );

        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $view->id);
        self::assertSame('John', $view->firstName);
        self::assertSame('Doe', $view->lastName);
        self::assertSame('active', $view->status);
        self::assertSame('+33612345678', $view->primaryPhone);
        self::assertSame('john@example.com', $view->primaryEmail);
        self::assertSame('2024-01-01T10:00:00+00:00', $view->createdAt);
    }

    public function testFullNameReturnsFormattedName(): void
    {
        $view = new ClientListItemView(
            id: '01234567-89ab-cdef-0123-456789abcdef',
            firstName: 'John',
            lastName: 'Doe',
            status: 'active',
            primaryPhone: null,
            primaryEmail: null,
            createdAt: '2024-01-01T10:00:00+00:00'
        );

        self::assertSame('John Doe', $view->fullName());
    }

    public function testFullNameTrimsExtraSpaces(): void
    {
        $view = new ClientListItemView(
            id: '01234567-89ab-cdef-0123-456789abcdef',
            firstName: '  John  ',
            lastName: '  Doe  ',
            status: 'active',
            primaryPhone: null,
            primaryEmail: null,
            createdAt: '2024-01-01T10:00:00+00:00'
        );

        self::assertSame('John     Doe', $view->fullName());
    }
}
