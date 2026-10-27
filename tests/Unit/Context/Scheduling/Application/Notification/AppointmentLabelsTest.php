<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Notification;

use App\Context\Scheduling\Application\Notification\AppointmentLabels;
use PHPUnit\Framework\TestCase;

final class AppointmentLabelsTest extends TestCase
{
    public function testTheLabelsOfTheReferenceSheets(): void
    {
        $at = new \DateTimeImmutable('2026-10-22 09:30', new \DateTimeZone('Europe/Paris'));

        self::assertSame('9h30', AppointmentLabels::time($at));
        self::assertSame('9h30 · 30 min', AppointmentLabels::timeAndDuration($at, 30));
        self::assertSame('jeudi 22/10 à 9h30', AppointmentLabels::slot($at));
        self::assertSame('jeudi 22 octobre à 9h30', AppointmentLabels::subjectDate($at));
        self::assertSame('Jeudi 22 octobre 2026', AppointmentLabels::date($at));
        self::assertSame('Jeu. 22 oct. · 9h30', AppointmentLabels::shortSlot($at));
        self::assertSame('Ven. 23 oct. · 11h00', AppointmentLabels::shortSlot(new \DateTimeImmutable('2026-10-23 11:00', new \DateTimeZone('Europe/Paris'))));
        self::assertSame('Dim. 1 févr. · 8h05', AppointmentLabels::shortSlot(new \DateTimeImmutable('2026-02-01 08:05', new \DateTimeZone('Europe/Paris'))));
    }

    public function testWhenIsSaidRelativeToToday(): void
    {
        $paris = new \DateTimeZone('Europe/Paris');
        $at    = new \DateTimeImmutable('2026-10-22 09:30', $paris);

        self::assertSame("aujourd'hui à 9h30", AppointmentLabels::when($at, new \DateTimeImmutable('2026-10-22 08:00', $paris)));
        self::assertSame('demain à 9h30', AppointmentLabels::when($at, new \DateTimeImmutable('2026-10-21 09:30', $paris)));
        self::assertSame('jeudi 22/10 à 9h30', AppointmentLabels::when($at, new \DateTimeImmutable('2026-10-20 09:30', $paris)));
    }

    public function testAMemberIsShortenedForTheBell(): void
    {
        self::assertSame('Julie M.', AppointmentLabels::shortName('Julie Martin'));
        self::assertSame('Claire L.', AppointmentLabels::shortName('Dr Claire Lambert'));
        self::assertSame('Anne-Marie V.', AppointmentLabels::shortName('Anne-Marie de  Vries'));
        self::assertSame('Dr Rousseau', AppointmentLabels::shortName('Dr Rousseau'));
        self::assertSame('Claire L.', AppointmentLabels::shortName('Dr. Claire Lambert'));
        self::assertSame('Claire L.', AppointmentLabels::shortName('Docteur Claire Lambert'));
        self::assertSame('Paul R.', AppointmentLabels::shortName('Pr. Paul Roux'));
        self::assertSame('Docteur Rousseau', AppointmentLabels::shortName('Docteur Rousseau'));
        self::assertSame('Julie', AppointmentLabels::shortName(' Julie '));
        self::assertSame('', AppointmentLabels::shortName(''));
    }
}
