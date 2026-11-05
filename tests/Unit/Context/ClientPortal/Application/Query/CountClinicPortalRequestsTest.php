<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query;

use App\Context\ClientPortal\Application\Port\PetState;
use App\Context\ClientPortal\Application\Query\CountClinicPortalRequests\CountClinicPortalRequests;
use App\Context\ClientPortal\Application\Query\CountClinicPortalRequests\CountClinicPortalRequestsHandler;
use App\Context\ClientPortal\Domain\Booking\DeclineReason;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Tests\Unit\Context\ClientPortal\Support\BookingRequestWorld as W;
use App\Tests\Unit\Context\ClientPortal\Support\PetFakes;
use App\Tests\Unit\Context\ClientPortal\Support\RequestFakes;
use PHPUnit\Framework\TestCase;

/** The « Demandes Portal » badge counts what the queue lists as waiting. */
final class CountClinicPortalRequestsTest extends TestCase
{
    private const string MOKA   = '01960000-0000-7000-8000-0000000e6001';
    private const string REX    = '01960000-0000-7000-8000-0000000e6002';
    private const string FILOU  = '01960000-0000-7000-8000-0000000e6003';
    private const string NALA   = '01960000-0000-7000-8000-0000000e6004';
    private const string STRAY  = '01960000-0000-7000-8000-0000000e6005';
    private const string FILE_A = '01960000-0000-7000-8000-0000000e5001';
    private const string FILE_B = '01960000-0000-7000-8000-0000000e5002';

    private W $world;
    private RequestFakes $requests;
    private PetFakes $pets;

    protected function setUp(): void
    {
        $this->world    = new W();
        $this->requests = new RequestFakes();
        $this->pets     = new PetFakes();
    }

    public function testEveryWaitingRequestIsCountedOnce(): void
    {
        // Two requests on Moka, one on Rex, one on a pet Animal does not know:
        // all four wait. Filou is archived and Nala deceased since their request.
        $this->requests->submittedPets = [self::MOKA, self::REX, self::MOKA, self::FILOU, self::NALA, self::STRAY];
        $this->state(self::MOKA);
        $this->state(self::REX);
        $this->state(self::FILOU, archived: true);
        $this->state(self::NALA, 'deceased');

        // One account asking for two files is one « Rattachement » request.
        $this->link('01960000-0000-7000-8000-0000000e7001', W::USER, self::FILE_A);
        $this->link('01960000-0000-7000-8000-0000000e7002', W::USER, self::FILE_B);
        $this->link('01960000-0000-7000-8000-0000000e7003', W::OTHER, self::FILE_A);
        // Another clinic's request, and a link already confirmed: not this queue.
        $this->link('01960000-0000-7000-8000-0000000e7004', W::OTHER, self::FILE_B, W::CLINIC_2);
        $this->world->links->save(ClientLink::confirm(ClientLinkId::fromString('01960000-0000-7000-8000-0000000e7005'), W::OTHER, W::CLINIC, self::FILE_B, W::utc(W::NOW)));

        $this->world->pending('01960000-0000-7000-8000-0000000e8001');
        $this->world->pending('01960000-0000-7000-8000-0000000e8002', createdAt: '2026-11-01 10:00:00');
        $this->world->pending('01960000-0000-7000-8000-0000000e8003', createdAt: '2026-11-01 11:00:00')->decline(DeclineReason::DECLINED, W::utc(W::NOW));
        $this->world->pending('01960000-0000-7000-8000-0000000e8004', W::CLINIC_2);

        // 4 pet requests + 2 accounts + 2 new clients.
        self::assertSame(8, $this->waiting());
        self::assertSame(['submitted:' . W::CLINIC], $this->requests->pages);
    }

    public function testAnEmptyQueueCountsNothing(): void
    {
        self::assertSame(0, $this->waiting());
    }

    private function waiting(): int
    {
        return new CountClinicPortalRequestsHandler($this->requests, $this->pets, $this->world->links, $this->world->requests)(new CountClinicPortalRequests(W::CLINIC));
    }

    private function state(string $petId, string $lifeStatus = 'alive', bool $archived = false): void
    {
        $this->pets->states[$petId] = new PetState($petId, 'Animal', null, 'neutered', null, $lifeStatus, $archived);
    }

    private function link(string $id, string $userId, string $clientId, string $clinicId = W::CLINIC): void
    {
        $this->world->links->save(ClientLink::requestFromSuggestion(ClientLinkId::fromString($id), $userId, $clinicId, $clientId, W::utc(W::NOW)));
    }
}
