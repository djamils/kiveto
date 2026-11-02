<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command;

use App\Context\ClientPortal\Application\Command\RevokeClientPortalAccess\RevokeClientPortalAccess;
use App\Context\ClientPortal\Application\Command\RevokeClientPortalAccess\RevokeClientPortalAccessHandler;
use App\Context\ClientPortal\Application\Service\PortalRequestCloser;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationStatus;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Tests\Unit\Context\ClientPortal\Support\ClientPortalWorld as W;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryChangeRequests;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryQuestionnaires;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryRenewalRequests;
use App\Tests\Unit\Context\ClientPortal\Support\Requests;
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

        $handler = new RevokeClientPortalAccessHandler($world->invitations, $world->links, $world->clock, $world->publisher(), self::closer($world));
        $handler(new RevokeClientPortalAccess(W::CLINIC, W::CLIENT));
        $handler(new RevokeClientPortalAccess(W::CLINIC, W::CLIENT));

        self::assertSame([PortalInvitationStatus::REVOKED, '2026-10-30'], [$world->invitation($pending)->status(), $world->invitation($pending)->revokedAt()?->format('Y-m-d')]);
        self::assertSame(PortalInvitationStatus::PENDING, $world->invitation($elsewhere)->status(), 'another client keeps its invitation');

        [$link] = array_values($world->links->links);
        self::assertSame([ClientLinkStatus::REVOKED, '2026-10-30'], [$link->status(), $link->revokedAt()?->format('Y-m-d')]);
        self::assertSame(['client_portal.client-link.revoked.v1'], $world->fakes->eventNames(), 'idempotent: revoked once');
    }

    public function testTheRequestsLeftOpenOnTheClientAreClosedAsLinkRevoked(): void
    {
        $world          = new W();
        $questionnaires = new InMemoryQuestionnaires();
        $changes        = new InMemoryChangeRequests();
        $renewals       = new InMemoryRenewalRequests();
        $questionnaires->save(Requests::questionnaire('01960000-0000-7000-8000-0000000f0001', 'rdv-1', clientId: W::CLIENT, clinicId: W::CLINIC));
        $changes->save(Requests::change('01960000-0000-7000-8000-0000000f0002', clientId: W::CLIENT, clinicId: W::CLINIC));
        $changes->save(Requests::change('01960000-0000-7000-8000-0000000f0003', clientId: W::CLIENT2, clinicId: W::CLINIC));
        $renewals->save(Requests::renewal('01960000-0000-7000-8000-0000000f0004', 'rx-1', clientId: W::CLIENT, clinicId: W::CLINIC));

        $handler = new RevokeClientPortalAccessHandler($world->invitations, $world->links, $world->clock, $world->publisher(), new PortalRequestCloser($questionnaires, $changes, $renewals, $world->clock));
        $handler(new RevokeClientPortalAccess(W::CLINIC, W::CLIENT));

        $closed = [
            $questionnaires->findByAppointment(W::CLINIC, 'rdv-1'),
            $changes->requests['01960000-0000-7000-8000-0000000f0002'],
            $renewals->requests['01960000-0000-7000-8000-0000000f0004'],
        ];

        foreach ($closed as $request) {
            self::assertNotNull($request);
            self::assertSame([RequestStatus::CLOSED, ClosureReason::LINK_REVOKED], [$request->status(), $request->closedReason()]);
        }

        self::assertSame(RequestStatus::SUBMITTED, $changes->requests['01960000-0000-7000-8000-0000000f0003']->status(), 'another client keeps its request');
    }

    private static function closer(W $world): PortalRequestCloser
    {
        return new PortalRequestCloser(new InMemoryQuestionnaires(), new InMemoryChangeRequests(), new InMemoryRenewalRequests(), $world->clock);
    }
}
