<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\PostNewAppointmentNotice;

use App\Context\Scheduling\Application\Command\PostNewAppointmentNotice\PostNewAppointmentNotice;
use App\Context\Scheduling\Application\Command\PostNewAppointmentNotice\PostNewAppointmentNoticeHandler;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use App\Tests\Unit\Context\Scheduling\Support\CapturingPractitionerBell;
use App\Tests\Unit\Context\Scheduling\Support\FakeAnimals;
use App\Tests\Unit\Context\Scheduling\Support\FakeClientContacts;
use App\Tests\Unit\Context\Scheduling\Support\FakeClinicDirectory;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryAppointments;
use PHPUnit\Framework\TestCase;

/**
 * AC 38: « Nouveau RDV · Milo » in Dr Rousseau's bell when Julie books for
 * him, nothing when he books himself.
 */
final class PostNewAppointmentNoticeHandlerTest extends TestCase
{
    private const string JULIE = '01960000-0000-7000-8000-0000000d0009';

    private InMemoryAppointments $appointments;
    private FakeClientContacts $contacts;
    private FakeAnimals $animals;
    private CapturingPractitionerBell $bell;

    protected function setUp(): void
    {
        $this->appointments = new InMemoryAppointments();
        $this->contacts     = new FakeClientContacts();
        $this->animals      = new FakeAnimals();
        $this->bell         = new CapturingPractitionerBell();
    }

    public function testTheVeterinarianHearsOfAnAppointmentBookedForThem(): void
    {
        $this->appointments->save(Appointments::planned());

        $this->handle(self::JULIE);

        self::assertSame([[
            'clinicId'      => Appointments::CLINIC,
            'userId'        => Appointments::ROUSSEAU,
            'appointmentId' => Appointments::ID,
            'date'          => '2026-10-22',
            'title'         => 'Nouveau RDV · Milo',
            'detail'        => 'Sophie Dubois · jeudi 22/10 à 9h30 · saisi par Julie M.',
            'key'           => 'new-appointment:' . Appointments::ID,
        ]], $this->bell->announced);
    }

    public function testNothingIsPostedToAVeterinarianWhoBookedThemself(): void
    {
        $this->appointments->save(Appointments::planned());

        $this->handle(Appointments::ROUSSEAU);

        self::assertSame([], $this->bell->announced);
    }

    public function testAnAppointmentGoneOrCancelledMeanwhileIsNotAnnounced(): void
    {
        $this->handle(self::JULIE);
        $this->appointments->save(Appointments::planned(status: AppointmentStatus::CANCELLED));
        $this->handle(self::JULIE);

        self::assertSame([], $this->bell->announced);
    }

    public function testWhatIsUnknownIsLeftOut(): void
    {
        // No animal, no owner, booked by the system: the slot alone.
        $this->appointments->save(Appointments::planned(ownerId: null, animalId: null));
        $this->handle(null);

        // An unknown animal and owner, an author without staff profile.
        $this->appointments->save(Appointments::planned());
        $this->animals->names     = [];
        $this->contacts->contacts = [];
        $this->handle('01960000-0000-7000-8000-0000000d0010');

        self::assertSame(['Nouveau RDV', 'Nouveau RDV'], array_column($this->bell->announced, 'title'));
        self::assertSame(['jeudi 22/10 à 9h30', 'jeudi 22/10 à 9h30'], array_column($this->bell->announced, 'detail'));
    }

    public function testTheSlotIsSaidInTheClinicTimeZone(): void
    {
        $this->appointments->save(Appointments::planned('2026-10-22 22:30'));

        $this->handle(self::JULIE, new FakeClinicDirectory(printedNames: [self::JULIE => 'Julie']));

        self::assertSame('2026-10-23', $this->bell->announced[0]['date']);
        self::assertSame('Sophie Dubois · vendredi 23/10 à 0h30 · saisi par Julie', $this->bell->announced[0]['detail']);
    }

    private function handle(?string $actor, ?FakeClinicDirectory $clinics = null): void
    {
        $handler = new PostNewAppointmentNoticeHandler(
            $this->appointments,
            $clinics ?? new FakeClinicDirectory(printedNames: [self::JULIE => 'Julie Martin']),
            $this->contacts,
            $this->animals,
            $this->bell,
        );

        $handler(new PostNewAppointmentNotice(Appointments::ID, $actor));
    }
}
