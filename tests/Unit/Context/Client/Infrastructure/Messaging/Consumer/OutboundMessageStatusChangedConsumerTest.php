<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Messaging\Consumer;

use App\Context\Client\Application\Command\RecordAutomatedCommunication\RecordAutomatedCommunication;
use App\Context\Client\Infrastructure\Messaging\Consumer\OutboundMessageStatusChangedConsumer;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Domain\Event\OutboundMessageStatusChangedIntegrationEvent;
use PHPUnit\Framework\TestCase;

final class OutboundMessageStatusChangedConsumerTest extends TestCase
{
    public function testAStatusOfAClientMessageIsJournaled(): void
    {
        $dispatched = [];
        $bus        = $this->createMock(CommandBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturnCallback(static function (object $command) use (&$dispatched): null {
            $dispatched[] = $command;

            return null;
        });

        new OutboundMessageStatusChangedConsumer($bus)($this->event('01234567-89ab-cdef-0123-456789abcdef', '12345678-9abc-def0-1234-56789abcdef0'));

        self::assertCount(1, $dispatched);
        self::assertSame((array) new RecordAutomatedCommunication(
            notificationMessageId: '0192a000-0000-7000-8000-0000000000e1',
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            clientId: '01234567-89ab-cdef-0123-456789abcdef',
            channel: 'SMS',
            subject: 'Rappel de rendez-vous',
            recipientLabel: 'Hélène Lambert',
            status: 'DELIVERED',
            occurredAt: '2026-10-26T09:42:30+00:00',
            sequence: 3,
        ), (array) $dispatched[0]);
    }

    public function testAMessageWithoutClientOrClinicIsNotJournaled(): void
    {
        $bus = $this->createMock(CommandBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $consumer = new OutboundMessageStatusChangedConsumer($bus);
        $consumer($this->event(null, '12345678-9abc-def0-1234-56789abcdef0'));
        $consumer($this->event('01234567-89ab-cdef-0123-456789abcdef', null));
    }

    private function event(?string $clientId, ?string $clinicId): OutboundMessageStatusChangedIntegrationEvent
    {
        return new OutboundMessageStatusChangedIntegrationEvent(
            id: '0192a000-0000-7000-8000-0000000000e1',
            clinicId: $clinicId,
            clientId: $clientId,
            purpose: 'APPOINTMENT_REMINDER',
            channel: 'SMS',
            recipientLabel: 'Hélène Lambert',
            subjectLine: 'Rappel de rendez-vous',
            status: 'DELIVERED',
            statusLabel: 'Délivré',
            reason: null,
            occurredAt: '2026-10-26T09:42:30+00:00',
            sequence: 3,
        );
    }
}
