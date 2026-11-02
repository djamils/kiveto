<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query;

use App\Context\ClientPortal\Application\Port\ClinicLabel;
use App\Context\ClientPortal\Application\Port\MobileFileMatch;
use App\Context\ClientPortal\Application\Query\GetBookingRequest\GetBookingRequest;
use App\Context\ClientPortal\Application\Query\GetBookingRequest\GetBookingRequestHandler;
use App\Context\ClientPortal\Application\Query\GetOwnerBookingRequest\GetOwnerBookingRequest;
use App\Context\ClientPortal\Application\Query\GetOwnerBookingRequest\GetOwnerBookingRequestHandler;
use App\Context\ClientPortal\Application\Query\ListClinicBookingRequests\BookingClientMatch;
use App\Context\ClientPortal\Application\Query\ListClinicBookingRequests\ListClinicBookingRequests;
use App\Context\ClientPortal\Application\Query\ListClinicBookingRequests\ListClinicBookingRequestsHandler;
use App\Context\ClientPortal\Application\Query\ListOwnerBookingRequests\ListOwnerBookingRequests;
use App\Context\ClientPortal\Application\Query\ListOwnerBookingRequests\ListOwnerBookingRequestsHandler;
use App\Context\ClientPortal\Application\Query\ListOwnerBookingRequests\OwnerBookingRequest;
use App\Context\ClientPortal\Domain\Booking\DeclineReason;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Tests\Unit\Context\ClientPortal\Support\BookingRequestWorld as W;
use PHPUnit\Framework\TestCase;

final class BookingRequestQueriesTest extends TestCase
{
    private const string FIRST  = '01960000-0000-7000-8000-0000000d0001';
    private const string SECOND = '01960000-0000-7000-8000-0000000d0002';
    private const string THIRD  = '01960000-0000-7000-8000-0000000d0003';

    private W $world;

    protected function setUp(): void
    {
        $this->world                              = new W();
        $this->world->fakes->clinics[W::CLINIC_2] = new ClinicLabel('Clinique du Port', null, 'America/Martinique');
    }

    public function testTheOwnerSeesItsRequestsNewestFirstPageByPage(): void
    {
        $this->world->pending(self::FIRST, createdAt: '2026-10-01 08:00:00')->decline(DeclineReason::EXPIRED, W::utc(W::NOW));
        $this->world->pending(self::SECOND, W::CLINIC_2, '2026-10-02 08:00:00')->confirm(W::CLIENT, W::utc(W::NOW));
        $this->world->pending(self::THIRD, createdAt: '2026-10-03 08:00:00', userId: W::OTHER);
        $handler = new ListOwnerBookingRequestsHandler($this->world->requests, $this->world->fakes);

        $page = $handler(new ListOwnerBookingRequests(W::USER, null, 1));
        self::assertSame([[self::SECOND, 'Clinique du Port', null, 'Pixel', 'cat', 'Première consultation', '2026-11-05T09:30:00-04:00', 'confirmed', null, self::SECOND]], self::rows($page->items));
        self::assertNotNull($page->nextCursor);

        $next = $handler(new ListOwnerBookingRequests(W::USER, $page->nextCursor, 1));
        self::assertSame([[self::FIRST, 'Clinique des Lilas', 'Lyon', 'Pixel', 'cat', 'Première consultation', '2026-11-05T14:30:00+01:00', 'declined', 'expired', null]], self::rows($next->items));
        self::assertNull($next->nextCursor);

        self::assertSame([], $handler(new ListOwnerBookingRequests(W::OTHER . 'x'))->items);
    }

    public function testOneRequestOfTheAccount(): void
    {
        $this->world->pending(self::FIRST);
        $handler = new GetOwnerBookingRequestHandler($this->world->requests, $this->world->fakes);

        self::assertSame(self::FIRST, $handler(new GetOwnerBookingRequest(W::USER, self::FIRST))?->id);
        self::assertNull($handler(new GetOwnerBookingRequest(W::OTHER, self::FIRST)));
    }

    public function testStaffSeeThePendingRequestsWithTheMatchingFiles(): void
    {
        $this->world->pending(self::FIRST);
        $this->world->pending(self::SECOND, createdAt: '2026-11-01 10:00:00')->decline(DeclineReason::DECLINED, W::utc(W::NOW));
        $this->world->fakes->mobileMatches[W::PHONE] = [
            new MobileFileMatch(W::CLINIC, W::CLIENT, 'Sophie', 'MARTIN', ['Oslo']),
            new MobileFileMatch(W::CLINIC, '01960000-0000-7000-8000-0000000c5002', 'Paul', 'Durand', []),
            new MobileFileMatch(W::CLINIC_2, '01960000-0000-7000-8000-0000000c5003', 'Sophie', 'Martin', []),
        ];
        $this->world->pets = [W::pet(W::PET, W::CLIENT, 'Oslo', 'dog')];
        $this->world->links->save(ClientLink::confirm(ClientLinkId::fromString('01960000-0000-7000-8000-0000000c7001'), W::OTHER, W::CLINIC, '01960000-0000-7000-8000-0000000c5002', W::utc('2026-10-01 08:00:00')));
        $this->world->links->save(ClientLink::confirm(ClientLinkId::fromString('01960000-0000-7000-8000-0000000c7002'), W::USER, W::CLINIC, W::CLIENT, W::utc('2026-10-01 08:00:00')));

        [$request] = new ListClinicBookingRequestsHandler($this->world->clinicRequests())(new ListClinicBookingRequests(W::CLINIC));

        self::assertSame([self::FIRST, W::USER, 'Sophie Martin', W::PHONE, '2026-11-05T14:30:00+01:00', '2026-11-01T10:00:00+01:00', 'pending', null, self::FIRST], [$request->id, $request->userId, $request->requesterName(), $request->requesterPhone, $request->startAt->format('c'), $request->createdAt->format('c'), $request->status, $request->attachedClientId, $request->appointmentId]);
        self::assertSame(
            [[W::CLIENT, 'Sophie MARTIN', [[W::PET, 'Oslo', 'dog']], false, false], ['01960000-0000-7000-8000-0000000c5002', 'Paul Durand', [], true, true]],
            array_map(static fn (BookingClientMatch $m): array => [$m->clientId, $m->name(), array_map(static fn ($p): array => [$p->id, $p->name, $p->species], $m->pets), $m->nameMismatch, $m->linkedToAnotherAccount], $request->matches),
        );
        self::assertSame([true, true], [$request->hasNameMismatch(), $request->hasFileLinkedElsewhere()]);

        self::assertSame([], new ListClinicBookingRequestsHandler($this->world->clinicRequests())(new ListClinicBookingRequests(W::CLINIC_2)));
    }

    public function testStaffSeeTheAnsweredRequestsLatestFirstWithWhoAnswered(): void
    {
        $this->world->pending(self::FIRST)->confirm(W::CLIENT, W::utc('2026-11-01 08:00:00'), W::VET, 'Dr Lemoine');
        $this->world->pending(self::SECOND)->decline(DeclineReason::EXPIRED, W::utc('2026-11-02 08:00:00'));
        $this->world->pending(self::THIRD);
        $handler = new ListClinicBookingRequestsHandler($this->world->clinicRequests());

        $decided = $handler(new ListClinicBookingRequests(W::CLINIC, true));

        self::assertSame([
            [self::SECOND, 'declined', '2026-11-02T09:00:00+01:00', null, []],
            [self::FIRST, 'confirmed', '2026-11-01T09:00:00+01:00', 'Dr Lemoine', []],
        ], array_map(static fn ($r): array => [$r->id, $r->status, $r->resolvedAt?->format('c'), $r->resolvedByName, $r->matches], $decided));
        self::assertSame([self::THIRD], array_map(static fn ($r): string => $r->id, $handler(new ListClinicBookingRequests(W::CLINIC))));
        self::assertSame([], $handler(new ListClinicBookingRequests(W::CLINIC_2, true)));
    }

    public function testOneRequestOfTheClinicResolvedHasNoMatch(): void
    {
        $this->world->pending(self::FIRST)->decline(DeclineReason::ACCOUNT_DELETED, W::utc(W::NOW));
        $this->world->requests->scrubUser(W::USER);
        $handler = new GetBookingRequestHandler($this->world->clinicRequests());

        $request = $handler(new GetBookingRequest(W::CLINIC, self::FIRST));
        self::assertSame([null, [], 'account_deleted', false, false], [$request?->requesterName(), $request?->matches, $request?->declineReason, $request?->hasNameMismatch(), $request?->hasFileLinkedElsewhere()]);
        self::assertNull($handler(new GetBookingRequest(W::CLINIC_2, self::FIRST)));
    }

    /**
     * @param list<mixed> $items
     *
     * @return list<list<mixed>>
     */
    private static function rows(array $items): array
    {
        return array_map(static function (mixed $item): array {
            \assert($item instanceof OwnerBookingRequest);

            return [$item->id, $item->clinicName, $item->clinicCity, $item->petName, $item->petSpecies, $item->motif, $item->startAt->format('c'), $item->status, $item->declineReason, $item->appointmentId];
        }, $items);
    }
}
