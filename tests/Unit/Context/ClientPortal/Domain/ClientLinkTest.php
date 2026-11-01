<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Domain;

use App\Context\ClientPortal\Domain\Event\ClientLinkConfirmed;
use App\Context\ClientPortal\Domain\Event\ClientLinkConfirmedIntegrationEvent;
use App\Context\ClientPortal\Domain\Event\ClientLinkRevoked;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use PHPUnit\Framework\TestCase;

final class ClientLinkTest extends TestCase
{
    private const string ID = '01960000-0000-7000-8000-00000000a001';

    public function testAConfirmedLinkGivesAccess(): void
    {
        $link = ClientLink::confirm(ClientLinkId::fromString(self::ID), 'user-1', 'clinic-1', 'client-1', new \DateTimeImmutable('2026-10-29 09:00'));

        self::assertSame([self::ID, 'user-1', 'clinic-1', 'client-1', ClientLinkStatus::CONFIRMED, '2026-10-29 09:00', null, true], [
            $link->id()->toString(), $link->userId(), $link->clinicId(), $link->clientId(), $link->status(), $link->linkedAt()?->format('Y-m-d H:i'), $link->revokedAt(), $link->isConfirmed(),
        ]);

        $events = $link->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClientLinkConfirmed::class, $events[0]);
        self::assertSame(['client_portal.client-link.confirmed.v1', self::ID], [$events[0]->name(), $events[0]->aggregateId()]);
        self::assertSame(['linkId' => self::ID, 'userId' => 'user-1', 'clinicId' => 'clinic-1', 'clientId' => 'client-1'], $events[0]->payload());
    }

    public function testRevocationThenReactivationWithANewLinkDate(): void
    {
        $link = ClientLink::confirm(ClientLinkId::fromString(self::ID), 'user-1', 'clinic-1', 'client-1', new \DateTimeImmutable('2026-10-29'));
        (void) $link->pullDomainEvents();

        $link->revoke(new \DateTimeImmutable('2026-11-01'));
        $link->revoke(new \DateTimeImmutable('2026-11-02'));
        self::assertSame([ClientLinkStatus::REVOKED, '2026-11-01', false], [$link->status(), $link->revokedAt()?->format('Y-m-d'), $link->isConfirmed()]);

        $events = $link->pullDomainEvents();
        self::assertCount(1, $events, 'revoked once');
        self::assertInstanceOf(ClientLinkRevoked::class, $events[0]);
        self::assertSame(['client_portal.client-link.revoked.v1', self::ID], [$events[0]->name(), $events[0]->aggregateId()]);
        self::assertSame(['linkId' => self::ID, 'userId' => 'user-1', 'clinicId' => 'clinic-1', 'clientId' => 'client-1', 'reason' => 'access_withdrawn'], $events[0]->payload());

        $link->reactivate(new \DateTimeImmutable('2026-11-10'));
        $link->reactivate(new \DateTimeImmutable('2026-11-11'));
        self::assertSame([ClientLinkStatus::CONFIRMED, '2026-11-10', null], [$link->status(), $link->linkedAt()?->format('Y-m-d'), $link->revokedAt()]);
        self::assertCount(1, $link->pullDomainEvents(), 'confirmed once');
    }

    public function testAPendingLinkIsConfirmedByReactivation(): void
    {
        $link = ClientLink::reconstitute(ClientLinkId::fromString(self::ID), 'user-1', 'clinic-1', 'client-1', ClientLinkStatus::PENDING, null, null);
        self::assertSame([], $link->recordedDomainEvents());
        self::assertNull($link->linkedAt());

        $link->reactivate(new \DateTimeImmutable('2026-11-10'));
        self::assertTrue($link->isConfirmed());
    }

    public function testTheIntegrationEventCarriesTheLink(): void
    {
        $event = new ClientLinkConfirmedIntegrationEvent(self::ID, 'user-1', 'clinic-1', 'client-1');

        self::assertSame(['client_portal.client-link-confirmed-integration.event.v1', self::ID], [$event->name(), $event->aggregateId()]);
        self::assertSame(['linkId' => self::ID, 'userId' => 'user-1', 'clinicId' => 'clinic-1', 'clientId' => 'client-1'], $event->payload());
    }
}
