<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Prescription\Infrastructure\Messaging;

use App\Context\Prescription\Domain\Event\PrescriptionCancelled;
use App\Context\Prescription\Domain\Event\PrescriptionCancelledIntegrationEvent;
use App\Context\Prescription\Domain\Event\PrescriptionIssued;
use App\Context\Prescription\Domain\Event\PrescriptionIssuedIntegrationEvent;
use App\Context\Prescription\Infrastructure\Messaging\Consumer\PrescriptionCancelledIntegrationBridge;
use App\Context\Prescription\Infrastructure\Messaging\Consumer\PrescriptionIssuedIntegrationBridge;
use App\Shared\Application\Bus\IntegrationEventBusInterface;
use App\Shared\Application\Event\IntegrationEventPublisher;
use PHPUnit\Framework\TestCase;

/**
 * The Client Portal learns about signed and cancelled ordonnances through the
 * outbox, with nothing but the ids.
 */
final class PrescriptionIntegrationBridgesTest extends TestCase
{
    private const string PRESCRIPTION_ID = '01960000-0000-7000-8000-000000000901';
    private const string CLINIC_ID       = '01960000-0000-7000-8000-000000000902';
    private const string AT              = '2026-08-22T10:00:00+00:00';

    public function testASignatureLeavesThroughTheOutbox(): void
    {
        $published = [];

        new PrescriptionIssuedIntegrationBridge($this->publisher($published))(new PrescriptionIssued(
            self::PRESCRIPTION_ID,
            self::CLINIC_ID,
            '01960000-0000-7000-8000-000000000903',
            'ORD-2026-000001',
            1,
            false,
            '2027-08-22T10:00:00+00:00',
            self::AT,
        ));

        self::assertCount(1, $published);
        self::assertInstanceOf(PrescriptionIssuedIntegrationEvent::class, $published[0]);
        self::assertSame(
            ['prescriptionId' => self::PRESCRIPTION_ID, 'clinicId' => self::CLINIC_ID],
            $published[0]->payload(),
        );
    }

    public function testACancellationLeavesThroughTheOutbox(): void
    {
        $published = [];

        new PrescriptionCancelledIntegrationBridge($this->publisher($published))(
            new PrescriptionCancelled(self::PRESCRIPTION_ID, self::CLINIC_ID, 'Erreur', self::AT),
        );

        self::assertCount(1, $published);
        self::assertInstanceOf(PrescriptionCancelledIntegrationEvent::class, $published[0]);
        self::assertSame(
            ['prescriptionId' => self::PRESCRIPTION_ID, 'clinicId' => self::CLINIC_ID],
            $published[0]->payload(),
        );
    }

    /**
     * @param list<object> $published
     */
    private function publisher(array &$published): IntegrationEventPublisher
    {
        $bus = $this->createMock(IntegrationEventBusInterface::class);
        $bus->expects(self::once())->method('publish')->willReturnCallback(
            static function (array $stamps, object ...$events) use (&$published): void {
                $published = array_values($events);
            },
        );

        return new IntegrationEventPublisher($bus);
    }
}
