<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Application\Query;

use App\Context\Scheduling\Application\Query\CountClinicAppointments\CountClinicAppointments;
use App\Context\Scheduling\Application\Query\CountClinicAppointments\CountClinicAppointmentsHandler;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/** The shell's « N RDV aujourd'hui »: the clinic's local day, the withdrawn appointments left out. */
final class CountClinicAppointmentsHandlerTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC = '01960000-0000-7000-8000-0000000f1c01';
    private const string OTHER  = '01960000-0000-7000-8000-0000000f1c02';

    public function testTheDaysAppointmentsButTheWithdrawnOnes(): void
    {
        $this->appointment(self::CLINIC, '2026-11-02 00:00:00');
        $this->appointment(self::CLINIC, '2026-11-02 09:00:00');
        $this->appointment(self::CLINIC, '2026-11-02 23:30:00');
        $this->appointment(self::CLINIC, '2026-11-02 10:00:00', AppointmentStatus::CANCELLED);
        $this->appointment(self::CLINIC, '2026-11-02 11:00:00', AppointmentStatus::DECLINED);
        // The day before, the day after, another clinic.
        $this->appointment(self::CLINIC, '2026-11-01 23:59:00');
        $this->appointment(self::CLINIC, '2026-11-03 00:00:00');
        $this->appointment(self::OTHER, '2026-11-02 09:00:00');

        $paris = new \DateTimeZone('Europe/Paris');
        $day   = new \DateTimeImmutable('2026-11-02 15:00:00', $paris);

        self::assertSame(3, $this->appointmentsIn(CountClinicAppointments::forDay(self::CLINIC, $day, $paris)));
        self::assertSame(0, $this->appointmentsIn(CountClinicAppointments::forDay(self::CLINIC, $day->modify('+7 days'), $paris)));
    }

    private function appointment(string $clinicId, string $parisStart, ?AppointmentStatus $status = null): void
    {
        $start   = new \DateTimeImmutable($parisStart, new \DateTimeZone('Europe/Paris'));
        $factory = AppointmentEntityFactory::new()->withClinicId($clinicId)->startingAt($start->setTimezone(new \DateTimeZone('UTC')), 30);

        (null === $status ? $factory : $factory->withStatus($status))->create();
    }

    private function appointmentsIn(CountClinicAppointments $query): int
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return new CountClinicAppointmentsHandler($connection)($query);
    }
}
