<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query;

use App\Context\ClientPortal\Application\Port\ClinicLabel;
use App\Context\ClientPortal\Application\Query\GetPortalInvitationByToken\GetPortalInvitationByToken;
use App\Context\ClientPortal\Application\Query\GetPortalInvitationByToken\GetPortalInvitationByTokenHandler;
use App\Context\ClientPortal\Application\Query\ListPendingInvitationsForPhone\ListPendingInvitationsForPhone;
use App\Context\ClientPortal\Application\Query\ListPendingInvitationsForPhone\ListPendingInvitationsForPhoneHandler;
use App\Context\ClientPortal\Domain\Exception\InvitationAlreadyUsedException;
use App\Context\ClientPortal\Domain\Exception\InvitationExpiredException;
use App\Context\ClientPortal\Domain\Exception\InvitationInvalidException;
use App\Tests\Unit\Context\ClientPortal\Support\ClientPortalWorld as W;
use PHPUnit\Framework\TestCase;

final class PortalInvitationQueriesTest extends TestCase
{
    public function testALiveTokenShowsTheInvitation(): void
    {
        $world                                           = new W();
        $world->fakes->clinics[W::CLINIC]                = new ClinicLabel('Clinique des Lilas', 'Lyon');
        $world->fakes->pets[W::CLINIC . '/' . W::CLIENT] = ['Moka', 'Pistache'];
        [$token, $id]                                    = $world->invite();

        $details = $this->lookup($world)(new GetPortalInvitationByToken($token));

        self::assertSame([$id, W::CLINIC, 'Clinique des Lilas', 'Lyon', 'sophie@example.com', W::PHONE, ['Moka', 'Pistache'], '2026-11-05 09:00'], [
            $details->invitationId, $details->clinicId, $details->clinicName, $details->city, $details->email, $details->phone, $details->petNames, $details->expiresAt->format('Y-m-d H:i'),
        ]);
    }

    public function testAnUnknownTokenIsInvalid(): void
    {
        $world = new W();
        $world->invite();

        $this->expectException(InvitationInvalidException::class);
        $this->expectExceptionMessage('Invitation not found.');
        $this->lookup($world)(new GetPortalInvitationByToken('nope'));
    }

    public function testAnExpiredTokenIsExpired(): void
    {
        $world   = new W();
        [$token] = $world->invite();
        $world->clock->advance('+7 days');

        $this->expectException(InvitationExpiredException::class);
        $this->lookup($world)(new GetPortalInvitationByToken($token));
    }

    public function testAnAcceptedOrRevokedTokenIsAlreadyUsed(): void
    {
        $world        = new W();
        [$token, $id] = $world->invite();
        $world->invitation($id)->revoke($world->clock->now());

        $this->expectException(InvitationAlreadyUsedException::class);
        $this->lookup($world)(new GetPortalInvitationByToken($token));
    }

    public function testThePendingInvitationsOfAPhoneAreListed(): void
    {
        $world                             = new W();
        $world->fakes->clinics[W::CLINIC2] = new ClinicLabel('Clinique du Parc', null);
        $world->fakes->withClient(W::CLINIC2, W::CLIENT2);
        [, $first] = $world->invite();
        $world->clock->advance('+1 day');
        [, $second] = $world->invite(W::CLINIC2, W::CLIENT2);
        $world->fakes->withClient(W::CLINIC2, '01960000-0000-7000-8000-00000000d003', '+33600000001');
        $world->invite(W::CLINIC2, '01960000-0000-7000-8000-00000000d003');

        $handler = new ListPendingInvitationsForPhoneHandler($world->invitations, $world->fakes, $world->clock);

        $list = $handler(new ListPendingInvitationsForPhone(W::PHONE));
        self::assertSame([[$first, '', null], [$second, 'Clinique du Parc', null]], array_map(static fn ($s): array => [$s->invitationId, $s->clinicName, $s->city], $list));

        $world->clock->advance('+6 days');
        self::assertCount(1, $handler(new ListPendingInvitationsForPhone(W::PHONE)), 'the first one expired');
        self::assertSame([], $handler(new ListPendingInvitationsForPhone('06 12')), 'a malformed phone has none');
    }

    private function lookup(W $world): GetPortalInvitationByTokenHandler
    {
        return new GetPortalInvitationByTokenHandler($world->invitations, $world->fakes, $world->fakes, $world->fakes, $world->clock);
    }
}
