<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Clinic\ViewModel\Agenda;

use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\AppointmentItem;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\GetAgendaForClinicDateRange;
use App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange\PlanningBlockView;
use App\Presentation\Clinic\ViewModel\Agenda\AgendaViewBuilder;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AgendaViewBuilderTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function provideMotifIsReadFromTheReasonCases(): iterable
    {
        yield 'empty' => [null, 'consult'];
        yield 'emergency' => ['Urgence — trauma', 'urg'];
        yield 'vaccine' => ['Consultation vaccinale', 'vaccin'];
        yield 'follow-up after surgery' => ['Contrôle post-stérilisation', 'suivi'];
        yield 'check-up' => ['Bilan gériatrique', 'bilan'];
        yield 'surgery' => ['Chirurgie — détartrage', 'chir'];
        yield 'anything else' => ['Consultation dermatologie', 'consult'];
    }

    #[DataProvider('provideMotifIsReadFromTheReasonCases')]
    public function testMotifIsReadFromTheReason(?string $reason, string $motif): void
    {
        self::assertSame($motif, AgendaViewBuilder::motif($reason));
    }

    public function testRangeGivesClinicLocalDaysAndMinutes(): void
    {
        $bus = new class implements QueryBusInterface {
            public ?GetAgendaForClinicDateRange $agenda = null;

            public function ask(object $query, object ...$stamps): mixed
            {
                if ($query instanceof GetAgendaForClinicDateRange) {
                    $this->agenda = $query;

                    return [new AppointmentItem(
                        id: 'a1',
                        clinicId: 'c1',
                        linkedAdmissionId: null,
                        practitionerUserId: 'u1',
                        startsAtUtc: '2026-10-14 07:30:00',
                        durationMinutes: 20,
                        status: 'NO_SHOW',
                        reason: 'Rappel vaccinal',
                        notes: null,
                        practitionerLabel: null,
                        ownerLabel: 'Emma Roux',
                        ownerPhone: '+33612345678',
                        animalLabel: 'Bisou',
                        animalSpecies: 'cat',
                    )];
                }

                return [new PlanningBlockView(
                    id: 'b1',
                    clinicId: 'c1',
                    staffUserId: 'u1',
                    type: 'on_call',
                    date: '2026-10-14',
                    startTime: '18:30:00',
                    endTime: '20:00:00',
                    capacityPerHour: 2,
                    recurrenceFreq: 'weekly',
                    recurrenceUntil: null,
                    note: 'Garde du soir',
                    acceptsAppointments: true,
                    hasCapacityLimit: true,
                )];
            }
        };

        $tz      = new \DateTimeZone('Europe/Paris');
        $builder = new AgendaViewBuilder($bus, $this->createStub(ClockInterface::class));
        $range   = $builder->range('c1', new \DateTimeImmutable('2026-10-14', $tz), new \DateTimeImmutable('2026-10-15', $tz), $tz);

        self::assertNotNull($bus->agenda);
        self::assertSame('2026-10-13 22:00:00', $bus->agenda->fromUtc->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-15 21:59:59', $bus->agenda->toUtc->format('Y-m-d H:i:s'));
        self::assertSame(['2026-10-14', '2026-10-15'], [$range['from'], $range['to']]);

        $appointment = $range['appointments'][0];
        self::assertSame(['2026-10-14', 570, 'vaccin', 'absent', '🐈', '06 12 34 56 78'], [
            $appointment['date'], $appointment['s'], $appointment['m'], $appointment['st'], $appointment['e'], $appointment['tel'],
        ]);
        self::assertSame(['id' => 'b1@2026-10-14', 'date' => '2026-10-14', 'vet' => 'u1', 's' => 1110, 'e' => 1200, 't' => 'garde', 'note' => 'Garde du soir'], $range['blocks'][0]);
    }
}
