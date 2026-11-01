<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Domain\Link;

use App\Context\ClientPortal\Domain\Event\ClientLinkConfirmed;
use App\Context\ClientPortal\Domain\Event\ClientLinkRevoked;
use App\Context\ClientPortal\Domain\Exception\ClientLinkNotPendingException;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use PHPUnit\Framework\TestCase;

/**
 * A link suggestion accepted by the owner (S7b): pending without access nor
 * link date, then confirmed or rejected (`link_rejected`) by the clinic.
 */
final class ClientLinkPendingTest extends TestCase
{
    private const string ID = '01960000-0000-7000-8000-00000000a101';

    public function testAnAcceptedSuggestionIsAPendingLinkWithoutAccess(): void
    {
        $link = $this->pending();

        self::assertSame([ClientLinkStatus::PENDING, null, null, '2026-11-02 10:00', true, false], [
            $link->status(), $link->linkedAt(), $link->revokedAt(), $link->requestedAt()?->format('Y-m-d H:i'), $link->isPending(), $link->isConfirmed(),
        ]);
        self::assertSame([], $link->pullDomainEvents());
    }

    public function testTheClinicConfirmsItOnce(): void
    {
        $link = $this->pending();

        $link->confirmPending(new \DateTimeImmutable('2026-11-03 08:00'));
        $link->confirmPending(new \DateTimeImmutable('2026-11-04 08:00'));

        self::assertSame([ClientLinkStatus::CONFIRMED, '2026-11-03 08:00', false], [$link->status(), $link->linkedAt()?->format('Y-m-d H:i'), $link->isPending()]);
        $events = $link->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClientLinkConfirmed::class, $events[0]);
    }

    public function testTheClinicRejectsItOnceWithoutTouchingAnythingElse(): void
    {
        $link = $this->pending();

        $link->reject(new \DateTimeImmutable('2026-11-03 08:00'));
        $link->reject(new \DateTimeImmutable('2026-11-04 08:00'));

        self::assertSame([ClientLinkStatus::REVOKED, '2026-11-03 08:00', null], [$link->status(), $link->revokedAt()?->format('Y-m-d H:i'), $link->linkedAt()]);
        $events = $link->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClientLinkRevoked::class, $events[0]);
        self::assertSame('link_rejected', $events[0]->payload()['reason']);
    }

    public function testARejectedLinkIsNotConfirmed(): void
    {
        $link = $this->pending();
        $link->reject(new \DateTimeImmutable('2026-11-03'));

        $this->expectException(ClientLinkNotPendingException::class);
        $this->expectExceptionMessage('is revoked, not pending');

        $link->confirmPending(new \DateTimeImmutable('2026-11-04'));
    }

    public function testAConfirmedLinkIsNotRejected(): void
    {
        $link = ClientLink::confirm(ClientLinkId::fromString(self::ID), 'user-1', 'clinic-1', 'client-1', new \DateTimeImmutable('2026-10-01'));

        try {
            $link->reject(new \DateTimeImmutable('2026-11-03'));
            self::fail('a confirmed link was rejected');
        } catch (ClientLinkNotPendingException $exception) {
            self::assertSame([self::ID, 'confirmed'], [$exception->linkId, $exception->status]);
        }

        self::assertTrue($link->isConfirmed());
    }

    private function pending(): ClientLink
    {
        return ClientLink::requestFromSuggestion(ClientLinkId::fromString(self::ID), 'user-1', 'clinic-1', 'client-1', new \DateTimeImmutable('2026-11-02 10:00'));
    }
}
