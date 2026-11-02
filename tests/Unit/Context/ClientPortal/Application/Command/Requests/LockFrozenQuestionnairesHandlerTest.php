<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\Requests;

use App\Context\ClientPortal\Application\Command\LockFrozenQuestionnaires\LockFrozenQuestionnaires;
use App\Context\ClientPortal\Application\Command\SubmitQuestionnaire\SubmitQuestionnaire;
use App\Context\ClientPortal\Application\Command\SubmitQuestionnaire\SubmitQuestionnaireOutcome;
use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as P;
use App\Tests\Unit\Context\ClientPortal\Support\Requests;
use App\Tests\Unit\Context\ClientPortal\Support\RequestWorld as W;
use PHPUnit\Framework\TestCase;

/** AC 232: the lock is recorded once the visit started, whoever looks at it. */
final class LockFrozenQuestionnairesHandlerTest extends TestCase
{
    private const string STARTED   = '01960000-0000-7000-8000-0000000b5001';
    private const string CONSULTED = '01960000-0000-7000-8000-0000000b5002';
    private const string UPCOMING  = '01960000-0000-7000-8000-0000000b5003';
    private const string UNKNOWN   = '01960000-0000-7000-8000-0000000b5004';
    private const string CLOSED    = '01960000-0000-7000-8000-0000000b5005';

    public function testTheSweepLocksTheQuestionnairesWhoseVisitStartedOrWasConsulted(): void
    {
        $world = new W();
        $world->appointment(self::STARTED, startsAt: '2026-10-29 08:30:00');
        $world->appointment(self::CONSULTED, startsAt: '2026-11-05 09:00:00');
        $world->appointment(self::UPCOMING, startsAt: '2026-11-05 10:00:00');
        $world->appointment(self::CLOSED, startsAt: '2026-10-29 08:00:00');
        $world->fakes->started = [self::CONSULTED];

        foreach ([self::STARTED, self::CONSULTED, self::UPCOMING, self::UNKNOWN, self::CLOSED] as $i => $appointmentId) {
            $world->questionnaires->save(Requests::questionnaire('01960000-0000-7000-8000-0000000e500' . $i, $appointmentId));
        }

        $world->questionnaires->findByAppointment(P::CLINIC, self::CLOSED)?->close(ClosureReason::APPOINTMENT_CANCELLED, Requests::utc('2026-10-28 10:00:00'));

        self::assertSame(2, $world->lockFrozen()(new LockFrozenQuestionnaires()));
        self::assertSame(0, $world->lockFrozen()(new LockFrozenQuestionnaires()), 'idempotent');

        $locked = static fn (string $appointmentId): ?string => $world->questionnaires->findByAppointment(P::CLINIC, $appointmentId)?->lockedAt()?->format('Y-m-d H:i:s');
        self::assertSame(['2026-10-29 09:00:00', '2026-10-29 09:00:00', null, null, null], array_map($locked, [self::STARTED, self::CONSULTED, self::UPCOMING, self::UNKNOWN, self::CLOSED]));
    }

    public function testAnsweredOnTheEveThenStartedThenMovedAWeekLaterStaysLocked(): void
    {
        $world = new W();
        $world->appointment(self::STARTED, startsAt: '2026-10-30 09:00:00');
        $command = new SubmitQuestionnaire(P::USER, P::CLINIC, self::STARTED, 1, ['cough'], false, null, 'calm', 'La veille');

        self::assertSame(SubmitQuestionnaireOutcome::SUBMITTED, $world->submitQuestionnaire()($command));

        // The visit starts; nobody opens the questionnaire, the sweep runs
        $world->pets->now('2026-10-30 09:05:00');
        self::assertSame(1, $world->lockFrozen()(new LockFrozenQuestionnaires()));

        // Staff move it to D+7
        $world->pets->fakes->appointments = [];
        $world->appointment(self::STARTED, startsAt: '2026-11-06 09:00:00');

        self::assertSame(SubmitQuestionnaireOutcome::LOCKED, $world->submitQuestionnaire()($command));
        self::assertSame('La veille', $world->questionnaires->findByAppointment(P::CLINIC, self::STARTED)?->answers()->notes());
    }
}
