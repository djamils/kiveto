<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query;

use App\Context\ClientPortal\Application\Port\ClinicLabel;
use App\Context\ClientPortal\Application\Query\GetClientPortalStatus\ClientPortalStatus;
use App\Context\ClientPortal\Application\Query\GetClientPortalStatus\GetClientPortalStatus;
use App\Context\ClientPortal\Application\Query\GetClientPortalStatus\GetClientPortalStatusHandler;
use App\Context\ClientPortal\Application\Query\GetOwnerProfile\GetOwnerProfile;
use App\Context\ClientPortal\Application\Query\GetOwnerProfile\GetOwnerProfileHandler;
use App\Context\ClientPortal\Application\Query\HasConfirmedClientLink\HasConfirmedClientLink;
use App\Context\ClientPortal\Application\Query\HasConfirmedClientLink\HasConfirmedClientLinkHandler;
use App\Context\ClientPortal\Application\Query\ListOwnerClinics\ListOwnerClinics;
use App\Context\ClientPortal\Application\Query\ListOwnerClinics\ListOwnerClinicsHandler;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Tests\Unit\Context\ClientPortal\Support\ClientPortalFakes;
use App\Tests\Unit\Context\ClientPortal\Support\ClientPortalWorld as W;
use PHPUnit\Framework\TestCase;

final class OwnerQueriesTest extends TestCase
{
    private const string USER = ClientPortalFakes::USER;

    public function testTheSheetStateFollowsInvitationsAndLinks(): void
    {
        $world  = new W();
        $status = fn (): ClientPortalStatus => new GetClientPortalStatusHandler($world->invitations, $world->links, $world->clock)(new GetClientPortalStatus(W::CLINIC, W::CLIENT));

        self::assertSame(ClientPortalStatus::NOT_INVITED, $status()->state);

        [$token, $id] = $world->invite();
        $invited      = $status();
        self::assertSame([ClientPortalStatus::INVITED, '2026-10-29', '2026-11-05', null, null], [$invited->state, $invited->sentAt?->format('Y-m-d'), $invited->expiresAt?->format('Y-m-d'), $invited->linkedAt, $invited->revokedAt]);

        $world->clock->advance('+7 days');
        self::assertSame([ClientPortalStatus::EXPIRED, '2026-10-29'], [$status()->state, $status()->sentAt?->format('Y-m-d')]);

        [$token] = $world->invite(); // « Renvoyer l'invitation »
        $world->acceptance()->acceptWithCode($token, 'c', '1', W::TERMS);
        self::assertSame([ClientPortalStatus::ACTIVE, '2026-11-05'], [$status()->state, $status()->linkedAt?->format('Y-m-d')]);

        foreach ($world->links->links as $link) {
            $link->revoke(new \DateTimeImmutable('2026-11-10', new \DateTimeZone('UTC')));
        }
        // An older revoked link of another account does not hide the latest withdrawal.
        $older = ClientLink::reconstitute(ClientLinkId::fromString('01960000-0000-7000-8000-0000000000a8'), 'other', W::CLINIC, W::CLIENT, ClientLinkStatus::REVOKED, null, new \DateTimeImmutable('2026-11-01', new \DateTimeZone('UTC')));
        $world->links->save($older);
        self::assertSame([ClientPortalStatus::REVOKED, '2026-11-10'], [$status()->state, $status()->revokedAt?->format('Y-m-d')]);

        $world->clock->now = new \DateTimeImmutable('2026-11-12 09:00', new \DateTimeZone('UTC'));
        $world->invite(); // « Réinviter »
        self::assertSame(ClientPortalStatus::INVITED, $status()->state);
    }

    public function testAPendingInvitationOlderThanTheWithdrawalShowsTheWithdrawal(): void
    {
        $world = new W();
        $world->invite();
        $world->links->save(ClientLink::reconstitute(ClientLinkId::fromString('01960000-0000-7000-8000-0000000000a7'), self::USER, W::CLINIC, W::CLIENT, ClientLinkStatus::REVOKED, null, new \DateTimeImmutable('2026-10-30')));
        $world->links->save(ClientLink::reconstitute(ClientLinkId::fromString('01960000-0000-7000-8000-0000000000a6'), 'x', W::CLINIC, W::CLIENT, ClientLinkStatus::PENDING, null, null));

        $status = new GetClientPortalStatusHandler($world->invitations, $world->links, $world->clock)(new GetClientPortalStatus(W::CLINIC, W::CLIENT));

        self::assertSame(ClientPortalStatus::REVOKED, $status->state);
    }

    public function testTheOwnerClinicsComeFromConfirmedLinksOneEntryPerClinic(): void
    {
        $world                             = new W();
        $world->fakes->clinics[W::CLINIC]  = new ClinicLabel('Clinique des Lilas', 'Lyon', 'Europe/Paris');
        $world->fakes->clinics[W::CLINIC2] = new ClinicLabel('Clinique du Parc', null);
        $world->fakes->pets                = [W::CLINIC . '/' . W::CLIENT => ['Moka'], W::CLINIC . '/' . W::CLIENT2 => ['Rex'], W::CLINIC2 . '/' . W::CLIENT => ['Nala']];
        $at                                = static fn (string $d): \DateTimeImmutable => new \DateTimeImmutable($d);
        $world->links->save(ClientLink::confirm(ClientLinkId::fromString('01960000-0000-7000-8000-0000000000b1'), self::USER, W::CLINIC, W::CLIENT2, $at('2026-10-20')));
        $world->links->save(ClientLink::confirm(ClientLinkId::fromString('01960000-0000-7000-8000-0000000000b2'), self::USER, W::CLINIC, W::CLIENT, $at('2026-10-10')));
        $world->links->save(ClientLink::confirm(ClientLinkId::fromString('01960000-0000-7000-8000-0000000000b3'), self::USER, W::CLINIC2, W::CLIENT, $at('2026-10-15')));
        $revoked = ClientLink::confirm(ClientLinkId::fromString('01960000-0000-7000-8000-0000000000b4'), self::USER, '01960000-0000-7000-8000-00000000c009', W::CLIENT, $at('2026-10-01'));
        $revoked->revoke($at('2026-10-02'));
        $world->links->save($revoked);

        $clinics = new ListOwnerClinicsHandler($world->links, $world->fakes, $world->fakes)(new ListOwnerClinics(self::USER));

        self::assertSame([
            [W::CLINIC, 'Clinique des Lilas', 'Lyon', '2026-10-10', ['Rex', 'Moka'], 'Europe/Paris'],
            [W::CLINIC2, 'Clinique du Parc', null, '2026-10-15', ['Nala'], 'UTC'],
        ], array_map(static fn ($c): array => [$c->clinicId, $c->clinicName, $c->city, $c->linkedAt->format('Y-m-d'), $c->petNames, $c->timeZone], $clinics));

        $links = new HasConfirmedClientLinkHandler($world->links);
        self::assertTrue($links(new HasConfirmedClientLink(self::USER, W::CLINIC2)));
        self::assertFalse($links(new HasConfirmedClientLink(self::USER, '01960000-0000-7000-8000-00000000c009')), 'revoked');
        self::assertFalse($links(new HasConfirmedClientLink('01960000-0000-7000-8000-0000000000ee', W::CLINIC)), 'missing');
        self::assertFalse($links(new HasConfirmedClientLink('nope', W::CLINIC)));
        self::assertFalse($links(new HasConfirmedClientLink(self::USER, 'nope')));
    }

    public function testTheProfileIsShapedForTheApi(): void
    {
        $world   = new W();
        $handler = new GetOwnerProfileHandler($world->profiles);
        self::assertNull($handler(new GetOwnerProfile('nobody')));

        $world->profiles->save(OwnerProfile::createFromClient(self::USER, 'Sophie', 'Martin', $world->fakes->snapshotOf(W::CLINIC, W::CLIENT)?->address, 'v1', new \DateTimeImmutable('2026-10-29')));
        $world->profiles->save(OwnerProfile::createFromClient('other', 'Paul', 'Durand', null, 'v1', new \DateTimeImmutable('2026-10-29')));

        $profile = $handler(new GetOwnerProfile(self::USER));
        self::assertNotNull($profile);
        self::assertSame(['Sophie', 'Martin', '12 rue des Lilas', 'Bât. B', '69003', 'Lyon', 'FR', 'v1', '2026-10-29'], [
            $profile->firstName, $profile->lastName, $profile->address?->line1, $profile->address?->line2, $profile->address?->postalCode, $profile->address?->city, $profile->address?->country, $profile->termsVersion, $profile->termsAcceptedAt->format('Y-m-d'),
        ]);
        self::assertNull($handler(new GetOwnerProfile('other'))?->address);
    }
}
