<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Service;

use App\Context\Scheduling\Application\Service\AppointmentParties;
use App\Context\Scheduling\Domain\Exception\AppointmentPartyNotFoundException;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use App\Tests\Unit\Context\Scheduling\Support\FakeAnimals;
use App\Tests\Unit\Context\Scheduling\Support\FakeClientContacts;
use PHPUnit\Framework\TestCase;

final class AppointmentPartiesTest extends TestCase
{
    private const string OTHER_CLINICS = '01960000-0000-7000-8000-00000000b0b0';

    public function testTheClientAndTheAnimalOfTheClinicAreAccepted(): void
    {
        $this->expectNotToPerformAssertions();

        $this->parties()->assertOfClinic(Appointments::CLINIC, Appointments::OWNER, Appointments::ANIMAL);
    }

    public function testAnAppointmentWithoutClientNorAnimalIsAccepted(): void
    {
        $this->expectNotToPerformAssertions();

        $this->parties()->assertOfClinic(Appointments::CLINIC, null, null);
    }

    public function testAClientOfAnotherClinicIsNotFound(): void
    {
        $this->expectException(AppointmentPartyNotFoundException::class);
        $this->expectExceptionMessage('Client ou animal introuvable.');

        $this->parties()->assertOfClinic(Appointments::CLINIC, self::OTHER_CLINICS, Appointments::ANIMAL);
    }

    public function testAnAnimalOfAnotherClinicIsNotFound(): void
    {
        $this->expectException(AppointmentPartyNotFoundException::class);
        $this->expectExceptionMessage('Client ou animal introuvable.');

        $this->parties()->assertOfClinic(Appointments::CLINIC, Appointments::OWNER, self::OTHER_CLINICS);
    }

    private function parties(): AppointmentParties
    {
        return new AppointmentParties(new FakeClientContacts(), new FakeAnimals());
    }
}
