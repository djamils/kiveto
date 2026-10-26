<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\GetClientNotificationContact;

use App\Context\Client\Application\Query\GetClientNotificationContact\GetClientNotificationContact;
use App\Context\Client\Application\Query\GetClientNotificationContact\GetClientNotificationContactHandler;
use App\Context\Client\Application\Query\GetClientsNotificationContacts\GetClientsNotificationContacts;
use App\Context\Client\Application\Query\GetClientsNotificationContacts\GetClientsNotificationContactsHandler;
use PHPUnit\Framework\TestCase;

final class GetClientNotificationContactHandlerTest extends TestCase
{
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';
    private const string CLIENT_A  = '01234567-89ab-cdef-0123-456789abcdef';
    private const string CLIENT_B  = '0192a000-0000-7000-8000-000000000009';

    public function testReturnsTheContactOfOneClientOrNull(): void
    {
        $reader  = $this->reader();
        $handler = new GetClientNotificationContactHandler($reader);

        self::assertSame(self::CLIENT_A, $handler(new GetClientNotificationContact(self::CLINIC_ID, self::CLIENT_A))?->clientId);
        self::assertNull($handler(new GetClientNotificationContact(self::CLINIC_ID, '0192a000-0000-7000-8000-00000000000f')));
        self::assertSame([[self::CLINIC_ID, [self::CLIENT_A]], [self::CLINIC_ID, ['0192a000-0000-7000-8000-00000000000f']]], $reader->calls);
    }

    public function testTheBatchQueryReadsEveryClientOnceInOneCall(): void
    {
        $reader  = $this->reader();
        $handler = new GetClientsNotificationContactsHandler($reader);

        $contacts = $handler(new GetClientsNotificationContacts(self::CLINIC_ID, [self::CLIENT_A, self::CLIENT_B, self::CLIENT_A]));

        self::assertSame([self::CLIENT_A, self::CLIENT_B], array_keys($contacts));
        self::assertSame([[self::CLINIC_ID, [self::CLIENT_A, self::CLIENT_B]]], $reader->calls);

        self::assertSame([], $handler(new GetClientsNotificationContacts(self::CLINIC_ID, [])));
        self::assertCount(1, $reader->calls, 'no query for an empty list');
    }

    private function reader(): RecordingContactReader
    {
        return new RecordingContactReader([self::CLIENT_A, self::CLIENT_B]);
    }
}
