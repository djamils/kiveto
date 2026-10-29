<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command;

use App\Context\ClientPortal\Application\Command\RevokeClientPortalAccess\RevokeClientPortalAccess;
use App\Context\ClientPortal\Application\Command\RevokeClientPortalAccess\RevokeClientPortalAccessHandler;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationStatus;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use App\Tests\Unit\Context\ClientPortal\Support\ClientPortalWorld as W;
use PHPUnit\Framework\TestCase;

final class RevokeClientPortalAccessHandlerTest extends TestCase
{
    public function testTheArchivedClientLosesItsPendingInvitationAndItsLinks(): void
    {
        $world   = new W();
        [$token] = $world->invite();
        $world->acceptance()->acceptWithCode($token, 'c', '1', W::TERMS);
        [, $pending] = $world->invite(); // « Réinviter »-like: a second, pending invitation
        $world->fakes->withClient(W::CLINIC2, W::CLIENT2);
        [, $elsewhere]        = $world->invite(W::CLINIC2, W::CLIENT2);
        $world->fakes->events = [];
        $world->clock->advance('+1 day');

        $handler = new RevokeClientPortalAccessHandler($world->invitations, $world->links, $world->clock, $world->publisher());
        $handler(new RevokeClientPortalAccess(W::CLINIC, W::CLIENT));
        $handler(new RevokeClientPortalAccess(W::CLINIC, W::CLIENT));

        self::assertSame([PortalInvitationStatus::REVOKED, '2026-10-30'], [$world->invitation($pending)->status(), $world->invitation($pending)->revokedAt()?->format('Y-m-d')]);
        self::assertSame(PortalInvitationStatus::PENDING, $world->invitation($elsewhere)->status(), 'another client keeps its invitation');

        [$link] = array_values($world->links->links);
        self::assertSame([ClientLinkStatus::REVOKED, '2026-10-30'], [$link->status(), $link->revokedAt()?->format('Y-m-d')]);
        self::assertSame(['client_portal.client-link.revoked.v1'], $world->fakes->eventNames(), 'idempotent: revoked once');
    }
}
