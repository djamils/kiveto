<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Application\Query\GetClientById;

use App\Client\Application\Query\GetClientById\GetClientById;
use PHPUnit\Framework\TestCase;

final class GetClientByIdTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $query = new GetClientById(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            clientId: '01234567-89ab-cdef-0123-456789abcdef'
        );

        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $query->clinicId);
        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $query->clientId);
    }
}
