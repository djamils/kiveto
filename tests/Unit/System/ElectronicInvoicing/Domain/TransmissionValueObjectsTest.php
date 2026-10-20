<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Domain;

use App\System\ElectronicInvoicing\Domain\Event\TransmissionPrepared;
use App\System\ElectronicInvoicing\Domain\Transmission\DocumentReference;
use App\System\ElectronicInvoicing\Domain\Transmission\ElectronicAddress;
use App\System\ElectronicInvoicing\Domain\Transmission\FlowId;
use App\System\ElectronicInvoicing\Domain\Transmission\LifecycleStatusCode;
use App\System\ElectronicInvoicing\Domain\Transmission\TrackingId;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionKind;
use PHPUnit\Framework\TestCase;

final class TransmissionValueObjectsTest extends TestCase
{
    public function testEveryStatusHasAFrenchLabel(): void
    {
        foreach (LifecycleStatusCode::cases() as $code) {
            self::assertNotSame('', $code->label());
        }

        self::assertSame('Approuvée', LifecycleStatusCode::APPROVED->label());
        self::assertSame('Annulée', LifecycleStatusCode::CANCELLED->label());
        self::assertSame('Irrecevable', LifecycleStatusCode::INADMISSIBLE->label());
    }

    public function testActionStatuses(): void
    {
        $action = array_values(array_filter(LifecycleStatusCode::cases(), static fn (LifecycleStatusCode $c): bool => $c->requiresAction()));
        self::assertSame([207, 208, 210, 213, 221, 501], array_map(static fn (LifecycleStatusCode $c): int => $c->value, $action));
    }

    public function testIdentifiers(): void
    {
        self::assertSame('doc-1', TrackingId::fromString(' doc-1 ')->toString());
        self::assertTrue(TrackingId::fromString('a')->equals(TrackingId::fromString('a')));
        self::assertTrue(FlowId::fromString('f')->equals(FlowId::fromString('f')));
        self::assertSame(TransmissionKind::CREDIT_NOTE, TransmissionKind::fromTypeCode('381'));
        self::assertSame(TransmissionKind::INVOICE, TransmissionKind::fromTypeCode('380'));
    }

    public function testAnEmptyTrackingIdIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TrackingId::fromString('  ');
    }

    public function testATooLongFlowIdIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FlowId::fromString(str_repeat('x', 65));
    }

    public function testADocumentReferenceNeedsAHash(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DocumentReference::of('file', 'abc');
    }

    public function testAnElectronicAddressNeedsBothParts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ElectronicAddress::of('0225', ' ');
    }

    public function testTransmissionEventsCarryTheirSequence(): void
    {
        $event = new TransmissionPrepared('t', 'c', 'doc-1', 3, '2026-10-21T10:00:00+00:00');

        self::assertSame('t', $event->aggregateId());
        self::assertSame(3, $event->payload()['sequence']);
        self::assertSame('electronic_invoicing.transmission.prepared.v1', $event->name());
    }
}
