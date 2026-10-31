<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Messaging\Consumer;

use App\Context\Billing\Domain\Event\InvoiceCancelled;
use App\Context\Billing\Domain\Event\InvoiceCancelledIntegrationEvent;
use App\Context\Billing\Domain\Event\InvoiceIssued;
use App\Context\Billing\Domain\Event\InvoiceIssuedForPortalIntegrationEvent;
use App\Context\Billing\Infrastructure\Messaging\Consumer\InvoiceCancelledIntegrationBridge;
use App\Context\Billing\Infrastructure\Messaging\Consumer\InvoiceIssuedForPortalIntegrationBridge;
use App\Shared\Application\Bus\IntegrationEventBusInterface;
use App\Shared\Application\Event\IntegrationEventPublisher;
use PHPUnit\Framework\TestCase;

final class PortalIntegrationBridgesTest extends TestCase
{
    private const string INVOICE_ID = '01960000-0000-7000-8000-000000000b11';
    private const string CLINIC_ID  = '01960000-0000-7000-8000-000000000b12';
    private const string AT         = '2026-10-21T10:00:00+02:00';

    /** @var list<object> */
    private array $published = [];

    public function testEveryIssuedInvoiceIsAnnouncedToThePortalPrivateOnesIncluded(): void
    {
        $bridge = new InvoiceIssuedForPortalIntegrationBridge($this->publisher());

        $bridge(new InvoiceIssued(self::INVOICE_ID, self::CLINIC_ID, 'FAC-2026-000001', 6000, 'EUR', self::AT, 'B2C'));
        $bridge(new InvoiceIssued(self::INVOICE_ID, self::CLINIC_ID, 'FAC-2026-000001', 6000, 'EUR', self::AT, 'B2B'));

        self::assertCount(2, $this->published);

        foreach ($this->published as $event) {
            self::assertInstanceOf(InvoiceIssuedForPortalIntegrationEvent::class, $event);
            self::assertSame(['invoiceId' => self::INVOICE_ID, 'clinicId' => self::CLINIC_ID], $event->payload());
        }
    }

    public function testACancelledInvoiceIsAnnounced(): void
    {
        (new InvoiceCancelledIntegrationBridge($this->publisher()))(
            new InvoiceCancelled(self::INVOICE_ID, self::CLINIC_ID, 'Erreur de saisie', self::AT),
        );

        self::assertCount(1, $this->published);
        $event = $this->published[0];
        self::assertInstanceOf(InvoiceCancelledIntegrationEvent::class, $event);
        self::assertSame(['invoiceId' => self::INVOICE_ID, 'clinicId' => self::CLINIC_ID], $event->payload());
    }

    private function publisher(): IntegrationEventPublisher
    {
        $bus = $this->createMock(IntegrationEventBusInterface::class);
        $bus->expects(self::atLeastOnce())
            ->method('publish')
            ->willReturnCallback(function (array $stamps, object ...$events): void {
                $this->published = [...$this->published, ...array_values($events)];
            })
        ;

        return new IntegrationEventPublisher($bus);
    }
}
