<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query\Requests;

use App\Context\ClientPortal\Application\Query\GetQuestionnaire\GetQuestionnaire;
use App\Context\ClientPortal\Application\Query\ListQuestionnaireSummaries\ListQuestionnaireSummaries;
use App\Context\ClientPortal\Domain\Exception\AppointmentNotFoundException;
use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as P;
use App\Tests\Unit\Context\ClientPortal\Support\Requests;
use App\Tests\Unit\Context\ClientPortal\Support\RequestWorld as W;
use PHPUnit\Framework\TestCase;

/** AC 241, 244: the questionnaire for the owner, the agenda and the cockpit. */
final class QuestionnaireQueriesTest extends TestCase
{
    private const string ID    = '01960000-0000-7000-8000-0000000e0001';
    private const string OTHER = '01960000-0000-7000-8000-0000000b0009';

    public function testBeforeAnyAnswerTheQuestionnaireIsNotStartedAndEditable(): void
    {
        $world = new W();
        $world->appointment();

        $questionnaire = $world->getQuestionnaire()(new GetQuestionnaire(P::CLINIC, W::APPOINTMENT, P::USER));

        self::assertSame([W::APPOINTMENT, 1, 'not_started', null, null, true, false], [
            $questionnaire->appointmentId, $questionnaire->version, $questionnaire->status, $questionnaire->answers, $questionnaire->updatedAt, $questionnaire->editable, $questionnaire->mayBite,
        ]);
    }

    public function testASubmittedQuestionnaireCarriesItsAnswersInTheClinicTimeZone(): void
    {
        $world = new W();
        $world->appointment();
        $world->questionnaires->save(Requests::questionnaire(self::ID, W::APPOINTMENT, answers: Requests::answers('may_bite')));

        $questionnaire = $world->getQuestionnaire()(new GetQuestionnaire(P::CLINIC, W::APPOINTMENT, P::USER));

        self::assertSame(['submitted', '2026-10-28T09:00:00+01:00', true, true], [$questionnaire->status, $questionnaire->updatedAt?->format('c'), $questionnaire->editable, $questionnaire->mayBite]);
        self::assertSame([['cough'], true, 'Sirop', 'may_bite', 'Il tousse la nuit.'], [
            $questionnaire->answers?->symptoms, $questionnaire->answers?->hasOngoingTreatment, $questionnaire->answers?->ongoingTreatmentDetails,
            $questionnaire->answers?->temperament, $questionnaire->answers?->notes,
        ]);
    }

    public function testTheClinicReadsAnyQuestionnaireOfItsAppointments(): void
    {
        $world  = new W();
        $closed = Requests::questionnaire(self::ID, self::OTHER);
        $closed->close(ClosureReason::APPOINTMENT_CANCELLED, Requests::utc('2026-10-28 10:00:00'));
        $world->questionnaires->save($closed);

        // Unknown to Scheduling: never editable
        $questionnaire = $world->getQuestionnaire()(new GetQuestionnaire(P::CLINIC, self::OTHER));

        self::assertSame(['closed', false], [$questionnaire->status, $questionnaire->editable]);
    }

    public function testReadingAQuestionnaireItsAppointmentFrozeRecordsTheLock(): void
    {
        // Answered on D-1, the visit starts, staff move it to D+7: still locked (AC 232)
        $world = new W();
        $world->appointment(startsAt: '2026-10-30 09:00:00');
        $world->questionnaires->save(Requests::questionnaire(self::ID, W::APPOINTMENT, at: '2026-10-29 09:00:00'));
        $world->pets->now('2026-10-30 09:05:00');

        self::assertFalse($world->getQuestionnaire()(new GetQuestionnaire(P::CLINIC, W::APPOINTMENT))->editable);
        self::assertSame('2026-10-30 09:05:00', $world->questionnaires->findByAppointment(P::CLINIC, W::APPOINTMENT)?->lockedAt()?->format('Y-m-d H:i:s'));

        $world->pets->fakes->appointments = [];
        $world->appointment(startsAt: '2026-11-06 09:00:00');

        self::assertFalse($world->getQuestionnaire()(new GetQuestionnaire(P::CLINIC, W::APPOINTMENT, P::USER))->editable);
        self::assertSame(2, $world->questionnaires->saves, 'saved, then the lock recorded once');
    }

    public function testAQuestionnaireOfAnAppointmentNoLongerHoldingItsSlotIsNotLockedByAReading(): void
    {
        $world = new W();
        $world->appointment(status: 'pending', occupies: false);
        $world->questionnaires->save(Requests::questionnaire(self::ID, W::APPOINTMENT));

        self::assertFalse($world->getQuestionnaire()(new GetQuestionnaire(P::CLINIC, W::APPOINTMENT, P::USER))->editable);
        self::assertNull($world->questionnaires->findByAppointment(P::CLINIC, W::APPOINTMENT)?->lockedAt());
    }

    public function testACancelledAppointmentHidesItsQuestionnaireFromTheOwner(): void
    {
        $world = new W();
        $world->appointment(status: 'cancelled_by_clinic', occupies: false);

        $this->expectException(AppointmentNotFoundException::class);
        $world->getQuestionnaire()(new GetQuestionnaire(P::CLINIC, W::APPOINTMENT, P::USER));
    }

    public function testTheAgendaBadgeListsTheSubmittedQuestionnairesOfTheRange(): void
    {
        $world = new W();
        $world->questionnaires->save(Requests::questionnaire(self::ID, W::APPOINTMENT, answers: Requests::answers('may_bite')));
        $closed = Requests::questionnaire('01960000-0000-7000-8000-0000000e0002', self::OTHER);
        $closed->close(ClosureReason::APPOINTMENT_DECLINED, Requests::utc('2026-10-28 10:00:00'));
        $world->questionnaires->save($closed);

        $summaries = $world->summaries()(new ListQuestionnaireSummaries(P::CLINIC, [W::APPOINTMENT, self::OTHER]));

        self::assertCount(1, $summaries);
        self::assertSame([W::APPOINTMENT, '2026-10-28T09:00:00+01:00', true], [$summaries[0]->appointmentId, $summaries[0]->updatedAt->format('c'), $summaries[0]->mayBite]);
        self::assertSame([], $world->summaries()(new ListQuestionnaireSummaries(P::CLINIC, [self::OTHER])));
    }
}
