<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Agenda;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\AppointmentItem;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\GetAgendaForClinicDateRange;
use App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange\ListPlanningBlocksForClinicDateRange;
use App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange\PlanningBlockView;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;

/**
 * Data of the agenda page (docs/frontend-theme/kiveto-theme/agenda-v-topbar_5.html):
 * the practitioners' columns, the bookings and the planning blocks of a date
 * range, in clinic-local dates and minutes since midnight — the shape the
 * mock-up's grid is drawn from (assets/js/pages/scheduling/agenda.js).
 *
 * @phpstan-type AgendaVet array{id: string, nom: string, court: string, ini: string, c: string}
 * @phpstan-type AgendaAppointment array{
 *     id: string, date: string, s: int, dur: int, vet: string,
 *     a: ?string, e: string, cli: ?string, tel: ?string,
 *     animalId: ?string, ownerId: ?string,
 *     m: string, reason: ?string, note: ?string, st: ?string
 * }
 * @phpstan-type AgendaBlock array{id: string, date: string, vet: string, s: int, e: int, t: string, note: ?string}
 * @phpstan-type AgendaRange array{from: string, to: string, appointments: list<AgendaAppointment>, blocks: list<AgendaBlock>}
 */
final readonly class AgendaViewBuilder
{
    /** Widest range a single fetch may cover (a mini-calendar month is 42 days). */
    public const int MAX_RANGE_DAYS = 62;

    private const array SPECIES_EMOJI = ['dog' => '🐕', 'cat' => '🐈', 'nac' => '🐇', 'rabbit' => '🐇'];

    /** Colours of the mock-up's practitioners, used when a member has not picked one. */
    private const array PALETTE = ['#4f86c6', '#6dbe78', '#c67db5', '#e0a458', '#5bb5b0', '#9a7fd1'];

    /** Planning block types as the mock-up names them (trames, gardes, congés). */
    private const array BLOCK_TYPE = [
        'consultation' => 'consult',
        'health_check' => 'bilan',
        'surgery'      => 'chir',
        'emergency'    => 'garde',
        'on_call'      => 'garde',
        'admin'        => 'admin',
        'training'     => 'formation',
        'leave'        => 'conge',
        'break'        => 'pause',
    ];

    private const array STATUS = ['CANCELLED' => 'annule', 'NO_SHOW' => 'absent', 'COMPLETED' => 'fait'];

    public function __construct(
        private QueryBusInterface $queryBus,
        private ClockInterface $clock,
    ) {
    }

    public function timeZone(string $clinicId): \DateTimeZone
    {
        $clinic = $this->queryBus->ask(new GetClinic($clinicId));
        \assert($clinic instanceof ClinicDto);

        return TimeZone::fromString($clinic->timeZone)->toNative();
    }

    /**
     * The page payload: today, the selected period and the data of the
     * 6-week grid of the selected month, which holds the selected week.
     *
     * @return array{
     *     today: string, nowMin: int, view: string, date: string, me: string,
     *     vets: list<AgendaVet>, range: AgendaRange
     * }
     */
    public function page(string $clinicId, string $userId, ?string $date, ?string $view): array
    {
        $tz    = $this->timeZone($clinicId);
        $now   = $this->clock->now()->setTimezone($tz);
        $today = $now->setTime(0, 0);

        $selected = $today;
        if (null !== $date && 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
            if (false !== $parsed && $parsed->format('Y-m-d') === $date) {
                $selected = $parsed;
            }
        }

        $firstOfMonth = $selected->modify('first day of this month');
        $gridStart    = $this->monday($firstOfMonth);

        return [
            'today'  => $today->format('Y-m-d'),
            'nowMin' => (int) $now->format('G') * 60 + (int) $now->format('i'),
            'view'   => 'day' === $view ? 'day' : 'week',
            'date'   => $selected->format('Y-m-d'),
            'me'     => $userId,
            'vets'   => $this->vets($clinicId, $userId),
            'range'  => $this->range($clinicId, $gridStart, $gridStart->modify('+41 days'), $tz),
        ];
    }

    /**
     * Bookings and planning blocks between two clinic-local days (inclusive).
     *
     * @return AgendaRange
     */
    public function range(string $clinicId, \DateTimeImmutable $from, \DateTimeImmutable $to, ?\DateTimeZone $tz = null): array
    {
        $tz ??= $this->timeZone($clinicId);
        $utc  = new \DateTimeZone('UTC');
        $from = $from->setTimezone($tz)->setTime(0, 0);
        $to   = $to->setTimezone($tz)->setTime(0, 0);

        $items = $this->queryBus->ask(new GetAgendaForClinicDateRange(
            clinicId: $clinicId,
            fromUtc: $from->setTimezone($utc),
            toUtc: $to->modify('+1 day')->modify('-1 second')->setTimezone($utc),
        ));
        \assert(\is_array($items));

        $appointments = [];
        foreach ($items as $item) {
            \assert($item instanceof AppointmentItem);
            $appointments[] = $this->appointment($item, $tz);
        }

        $views = $this->queryBus->ask(new ListPlanningBlocksForClinicDateRange(
            clinicId: $clinicId,
            fromDate: $from->format('Y-m-d'),
            toDate: $to->format('Y-m-d'),
        ));
        \assert(\is_array($views));

        $blocks = [];
        foreach ($views as $view) {
            \assert($view instanceof PlanningBlockView);
            $blocks[] = [
                'id'   => $view->id . '@' . $view->date,
                'date' => $view->date,
                'vet'  => $view->staffUserId,
                's'    => $this->minutes($view->startTime),
                'e'    => $this->minutes($view->endTime),
                't'    => self::BLOCK_TYPE[$view->type] ?? 'consult',
                'note' => $view->note,
            ];
        }

        return [
            'from'         => $from->format('Y-m-d'),
            'to'           => $to->format('Y-m-d'),
            'appointments' => $appointments,
            'blocks'       => $blocks,
        ];
    }

    /**
     * The motif chip of a booking, read from its free-text reason.
     */
    public static function motif(?string $reason): string
    {
        $r = mb_strtolower(trim((string) $reason));

        // Most specific first: « Contrôle post-stérilisation » is a follow-up, not a surgery
        return match (true) {
            '' === $r                                                                       => 'consult',
            1 === preg_match('/urgen|trauma|avp|intoxication|dyspn/u', $r)                  => 'urg',
            1 === preg_match('/vaccin|rappel|primo/u', $r)                                  => 'vaccin',
            1 === preg_match('/suivi|contrôle|retrait|post-op/u', $r)                       => 'suivi',
            str_contains($r, 'bilan')                                                       => 'bilan',
            1 === preg_match('/chirurg|stérilis|castration|détartrage|exérèse|ostéo/u', $r) => 'chir',
            default                                                                         => 'consult',
        };
    }

    /**
     * The clinic's practitioners shown in the agenda, in its colours
     * (also the planning's columns).
     *
     * @return list<AgendaVet>
     */
    public function vets(string $clinicId, string $userId): array
    {
        $items = $this->queryBus->ask(new ListClinicVeterinarians($clinicId));
        \assert(\is_array($items));

        $vets = [];
        foreach ($items as $item) {
            \assert($item instanceof ClinicVeterinarianItem);
            if (false === $item->isVisibleInAgenda) {
                continue;
            }

            $name  = trim($item->displayName ?? '') ?: 'Praticien';
            $title = ['DR' => 'Dr ', 'PR' => 'Pr '][$item->professionalTitle ?? ''] ?? '';

            $vets[] = [
                'id'    => $item->userId,
                'nom'   => $title . $name,
                'court' => $item->userId === $userId ? 'Moi' : $title . $name,
                'ini'   => $this->initials($name),
                'c'     => $item->agendaColor ?? self::PALETTE[\count($vets) % \count(self::PALETTE)],
            ];
        }

        return $vets;
    }

    /**
     * @return AgendaAppointment
     */
    private function appointment(AppointmentItem $item, \DateTimeZone $tz): array
    {
        $start = new \DateTimeImmutable($item->startsAtUtc, new \DateTimeZone('UTC'))->setTimezone($tz);

        return [
            'id'       => $item->id,
            'date'     => $start->format('Y-m-d'),
            's'        => (int) $start->format('G') * 60 + (int) $start->format('i'),
            'dur'      => $item->durationMinutes,
            'vet'      => $item->practitionerUserId,
            'a'        => $item->animalLabel,
            'e'        => self::SPECIES_EMOJI[strtolower($item->animalSpecies ?? '')] ?? '🐾',
            'cli'      => $item->ownerLabel,
            'tel'      => null === $item->ownerPhone ? null : $this->phone($item->ownerPhone),
            'animalId' => $item->animalId,
            'ownerId'  => $item->ownerId,
            'm'        => self::motif($item->reason),
            'reason'   => $item->reason,
            'note'     => $item->notes,
            'st'       => self::STATUS[$item->status] ?? null,
        ];
    }

    private function monday(\DateTimeImmutable $day): \DateTimeImmutable
    {
        return $day->modify(\sprintf('-%d days', (int) $day->format('N') - 1));
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time) + [1 => '0']);

        return $h * 60 + $m;
    }

    private function initials(string $name): string
    {
        $words = preg_split('/[\s-]+/u', $name, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $first = mb_substr($words[0] ?? '?', 0, 1);
        $last  = \count($words) > 1 ? mb_substr($words[\count($words) - 1], 0, 1) : mb_substr($words[0] ?? '', 1, 1);

        return mb_strtoupper($first . $last);
    }

    /** « +33612345678 » reads « 06 12 34 56 78 »; anything else is kept as typed. */
    private function phone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '33') && 11 === \strlen($digits)) {
            $digits = '0' . substr($digits, 2);
        }

        return 10 === \strlen($digits) ? implode(' ', str_split($digits, 2)) : $phone;
    }
}
