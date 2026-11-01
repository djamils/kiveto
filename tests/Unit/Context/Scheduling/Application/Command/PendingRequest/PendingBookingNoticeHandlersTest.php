<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\PendingRequest;

use App\Context\Scheduling\Application\Command\PostPendingBookingNotice\PostPendingBookingNotice;
use App\Context\Scheduling\Application\Command\PostPendingBookingNotice\PostPendingBookingNoticeHandler;
use App\Context\Scheduling\Application\Command\WithdrawPendingBookingNotice\WithdrawPendingBookingNotice;
use App\Context\Scheduling\Application\Command\WithdrawPendingBookingNotice\WithdrawPendingBookingNoticeHandler;
use App\Context\Scheduling\Domain\ValueObject\DeclineReason;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use App\Tests\Unit\Context\Scheduling\Support\CapturingPractitionerBell;
use App\Tests\Unit\Context\Scheduling\Support\FakeAnimals;
use App\Tests\Unit\Context\Scheduling\Support\FakeClientContacts;
use App\Tests\Unit\Context\Scheduling\Support\FakeClinicDirectory;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryAppointments;
use PHPUnit\Framework\TestCase;

/**
 * AC 39: « RDV en ligne à confirmer · Milo » in the practitioner's bell,
 * withdrawn once the request is handled.
 */
final class PendingBookingNoticeHandlersTest extends TestCase
{
    private InMemoryAppointments $appointments;
    private FakeAnimals $animals;
    private CapturingPractitionerBell $bell;

    protected function setUp(): void
    {
        $this->appointments = new InMemoryAppointments();
        $this->animals      = new FakeAnimals();
        $this->bell         = new CapturingPractitionerBell();
    }

    public function testThePractitionerHearsOfAPendingRequest(): void
    {
        $this->appointments->save(Appointments::pending());

        $this->post();

        self::assertSame([[
            'clinicId'      => Appointments::CLINIC,
            'userId'        => Appointments::ROUSSEAU,
            'appointmentId' => Appointments::ID,
            'date'          => '2026-10-22',
            'title'         => 'RDV en ligne à confirmer · Milo',
            'detail'        => 'Sophie Dubois · jeudi 22/10 à 9h30',
            'key'           => 'online-booking-pending:' . Appointments::ID,
        ]], $this->bell->pendingBookings);
        self::assertSame([], $this->bell->announced, 'no « Nouveau RDV »');
    }

    public function testANewClientRequestIsAnnouncedWithoutOwnerNorAnimalName(): void
    {
        $this->appointments->save(Appointments::pending(ownerId: null));
        $this->animals->names = [];

        $this->post();

        self::assertSame(['RDV en ligne à confirmer', 'jeudi 22/10 à 9h30'], [$this->bell->pendingBookings[0]['title'] ?? null, $this->bell->pendingBookings[0]['detail'] ?? null]);
    }

    public function testARequestHandledOrGoneBeforeTheWorkerRunsIsNotAnnounced(): void
    {
        $this->post();

        $declined = Appointments::pending();
        $declined->decline(DeclineReason::DECLINED);
        $this->appointments->save($declined);
        $this->post();

        self::assertSame([], $this->bell->pendingBookings);
    }

    public function testTheEntryIsWithdrawnByItsKey(): void
    {
        new WithdrawPendingBookingNoticeHandler($this->bell)(new WithdrawPendingBookingNotice(Appointments::ID));

        self::assertSame(['online-booking-pending:' . Appointments::ID], $this->bell->withdrawn);
    }

    private function post(): void
    {
        new PostPendingBookingNoticeHandler($this->appointments, new FakeClinicDirectory(), new FakeClientContacts(), $this->animals, $this->bell)(
            new PostPendingBookingNotice(Appointments::ID),
        );
    }
}
