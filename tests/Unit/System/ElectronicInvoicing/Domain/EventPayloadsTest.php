<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Domain;

use App\System\ElectronicInvoicing\Domain\Event\InvoiceTransmissionStatusChangedIntegrationEvent;
use App\System\ElectronicInvoicing\Domain\Event\LifecycleStatusRecorded;
use App\System\ElectronicInvoicing\Domain\Event\TransmissionBlocked;
use App\System\ElectronicInvoicing\Domain\Event\TransmissionNotRequired;
use App\System\ElectronicInvoicing\Domain\Event\TransmissionPrepared;
use App\System\ElectronicInvoicing\Domain\Event\TransmissionQueued;
use App\System\ElectronicInvoicing\Domain\Event\TransmissionSubmitted;
use App\System\ElectronicInvoicing\Domain\Event\TransmissionSubmitting;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventPayloadsTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<TransmissionPrepared|TransmissionQueued|TransmissionSubmitting|TransmissionSubmitted|TransmissionBlocked|TransmissionNotRequired|LifecycleStatusRecorded>, string}>
     */
    public static function provideTransmissionEventsCarryTheirSequenceCases(): iterable
    {
        yield 'prepared' => [TransmissionPrepared::class, 'electronic_invoicing.transmission.prepared.v1'];
        yield 'queued' => [TransmissionQueued::class, 'electronic_invoicing.transmission.queued.v1'];
        yield 'submitting' => [TransmissionSubmitting::class, 'electronic_invoicing.transmission.submitting.v1'];
        yield 'submitted' => [TransmissionSubmitted::class, 'electronic_invoicing.transmission.submitted.v1'];
        yield 'blocked' => [TransmissionBlocked::class, 'electronic_invoicing.transmission.blocked.v1'];
        yield 'not required' => [TransmissionNotRequired::class, 'electronic_invoicing.transmission-not.required.v1'];
        yield 'status' => [LifecycleStatusRecorded::class, 'electronic_invoicing.lifecycle-status.recorded.v1'];
    }

    /**
     * @param class-string<TransmissionPrepared|TransmissionQueued|TransmissionSubmitting|TransmissionSubmitted|TransmissionBlocked|TransmissionNotRequired|LifecycleStatusRecorded> $class
     */
    #[DataProvider('provideTransmissionEventsCarryTheirSequenceCases')]
    public function testTransmissionEventsCarryTheirSequence(string $class, string $name): void
    {
        $event = new $class('t-1', 'c-1', 'doc-1', 4, '2026-10-21T10:00:00+00:00');

        self::assertSame('t-1', $event->aggregateId());
        self::assertSame($name, $event->name());
        self::assertSame([
            'transmissionId' => 't-1',
            'clinicId'       => 'c-1',
            'trackingId'     => 'doc-1',
            'sequence'       => 4,
            'occurredAt'     => '2026-10-21T10:00:00+00:00',
        ], $event->payload());
    }

    public function testTheIntegrationEventCarriesTheCurrentView(): void
    {
        $event = new InvoiceTransmissionStatusChangedIntegrationEvent('c-1', 'doc-1', 7, 'accepted', null, 210, 'Refusée', 'DOUBLON', 'Facture en doublon', true, '2026-10-21T10:00:00+00:00');

        self::assertSame('doc-1', $event->aggregateId());
        self::assertSame('electronic_invoicing.invoice-transmission-status-changed-integration.event.v1', $event->name());
        self::assertSame([
            'clinicId'       => 'c-1',
            'documentId'     => 'doc-1',
            'sequence'       => 7,
            'state'          => 'accepted',
            'blockReason'    => null,
            'statusCode'     => 210,
            'statusLabel'    => 'Refusée',
            'reasonCode'     => 'DOUBLON',
            'reasonText'     => 'Facture en doublon',
            'requiresAction' => true,
            'occurredAt'     => '2026-10-21T10:00:00+00:00',
        ], $event->payload());
    }
}
