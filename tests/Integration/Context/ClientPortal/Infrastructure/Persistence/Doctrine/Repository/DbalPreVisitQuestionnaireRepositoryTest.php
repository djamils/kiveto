<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\PortalRequestId;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaire;
use App\Context\ClientPortal\Domain\Request\QuestionnaireAnswers;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Context\ClientPortal\Domain\Request\RequestSubject;
use App\Context\ClientPortal\Domain\Request\Symptom;
use App\Context\ClientPortal\Domain\Request\Temperament;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalPreVisitQuestionnaireRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalPreVisitQuestionnaireRepositoryTest extends KernelTestCase
{
    private const string Q1      = '01960000-0000-7000-8000-0000000a6001';
    private const string Q2      = '01960000-0000-7000-8000-0000000a6002';
    private const string Q3      = '01960000-0000-7000-8000-0000000a6003';
    private const string CLINIC  = '01960000-0000-7000-8000-0000000a6101';
    private const string CLINIC2 = '01960000-0000-7000-8000-0000000a6102';
    private const string CLIENT  = '01960000-0000-7000-8000-0000000a6201';
    private const string CLIENT2 = '01960000-0000-7000-8000-0000000a6202';
    private const string USER    = '01960000-0000-7000-8000-0000000a6301';
    private const string USER2   = '01960000-0000-7000-8000-0000000a6302';
    private const string RDV1    = '01960000-0000-7000-8000-0000000a6401';
    private const string RDV2    = '01960000-0000-7000-8000-0000000a6402';
    private const string RDV3    = '01960000-0000-7000-8000-0000000a6403';

    public function testAQuestionnaireIsReadBackWithEveryFieldAndRewritten(): void
    {
        $repository = $this->repository();
        $repository->save(self::questionnaire(self::Q1, self::RDV1));

        $found = $repository->findByAppointment(self::CLINIC, self::RDV1);
        self::assertNotNull($found);
        $answers = $found->answers();
        self::assertSame(
            [self::Q1, self::CLINIC, self::CLIENT, self::USER, self::RDV1, 1, [Symptom::COUGH, Symptom::ITCHING], true, 'Sirop', Temperament::MAY_BITE, 'Il tousse.', RequestStatus::SUBMITTED, '2026-10-28 08:00:00', '2026-10-28 08:00:00', null, null, null],
            [
                $found->id()->toString(), $found->clinicId(), $found->clientId(), $found->userId(), $found->appointmentId(), $found->version(),
                $answers->symptoms(), $answers->hasOngoingTreatment(), $answers->ongoingTreatmentDetails(), $answers->temperament(), $answers->notes(),
                $found->status(), $found->submittedAt()->format('Y-m-d H:i:s'), $found->updatedAt()->format('Y-m-d H:i:s'), $found->lockedAt(), $found->closedAt(), $found->closedReason(),
            ],
        );

        $found->answer(1, self::USER2, QuestionnaireAnswers::of(['none'], false, null, 'calm', null), self::utc('2026-10-28 09:00:00'));
        $found->lock(self::utc('2026-10-29 08:00:00'));
        $found->close(ClosureReason::APPOINTMENT_CANCELLED, self::utc('2026-10-29 09:00:00'));
        $repository->save($found);

        $rewritten = $repository->findByAppointmentForUpdate(self::CLINIC, self::RDV1);
        self::assertSame([self::USER2, [Symptom::NONE], false, null, Temperament::CALM, null, RequestStatus::CLOSED, '2026-10-29 08:00:00', '2026-10-29 09:00:00', ClosureReason::APPOINTMENT_CANCELLED], [
            $rewritten?->userId(), $rewritten?->answers()->symptoms(), $rewritten?->answers()->hasOngoingTreatment(), $rewritten?->answers()->ongoingTreatmentDetails(),
            $rewritten?->answers()->temperament(), $rewritten?->answers()->notes(), $rewritten?->status(), $rewritten?->lockedAt()?->format('Y-m-d H:i:s'),
            $rewritten?->closedAt()?->format('Y-m-d H:i:s'), $rewritten?->closedReason(),
        ]);

        self::assertNull($repository->findByAppointment(self::CLINIC2, self::RDV1), 'another clinic');
        self::assertNull($repository->findByAppointment(self::CLINIC, 'not-a-uuid'));
    }

    public function testTheQuestionnairesOfAppointmentsOfASubjectAndOfAUser(): void
    {
        $repository = $this->repository();
        $repository->save(self::questionnaire(self::Q1, self::RDV1));
        $repository->save(self::questionnaire(self::Q2, self::RDV2, client: self::CLIENT2, user: self::USER2, at: '2026-10-27 08:00:00'));
        $closed = self::questionnaire(self::Q3, self::RDV3, clinic: self::CLINIC2, at: '2026-10-26 08:00:00');
        $closed->close(ClosureReason::APPOINTMENT_DECLINED, self::utc('2026-10-28 08:00:00'));
        $repository->save($closed);

        $ids = static fn (array $questionnaires): array => array_map(static fn (mixed $q): string => $q instanceof PreVisitQuestionnaire ? $q->id()->toString() : '', $questionnaires);
        $set = $ids($repository->findByAppointments(self::CLINIC, [self::RDV1, self::RDV2, self::RDV3, 'not-a-uuid']));
        sort($set);
        self::assertSame([self::Q1, self::Q2], $set);
        self::assertSame([], $repository->findByAppointments(self::CLINIC, ['not-a-uuid']));

        self::assertSame([self::Q1], $ids($repository->findSubmittedForSubjectForUpdate(RequestSubject::CLIENT, self::CLIENT, self::CLINIC)));
        self::assertSame([self::Q2], $ids($repository->findSubmittedForSubjectForUpdate(RequestSubject::APPOINTMENT, self::RDV2, self::CLINIC)));
        self::assertSame([], $ids($repository->findSubmittedForSubjectForUpdate(RequestSubject::APPOINTMENT, self::RDV2, self::CLINIC2)));
        self::assertSame([self::Q1], $ids($repository->findSubmittedForSubjectForUpdate(RequestSubject::USER, self::USER, self::CLINIC2)), 'every clinic, closed left out');
        self::assertSame([], $repository->findSubmittedForSubjectForUpdate(RequestSubject::PET, self::RDV1, self::CLINIC), 'a questionnaire hangs on no pet');
        self::assertSame([self::Q3, self::Q1], $ids($repository->findByUser(self::USER)), 'oldest first, closed included');
    }

    public function testTheLockIsRecordedOnTheOpenQuestionnaireOnlyAndNeverUndone(): void
    {
        $repository = $this->repository();
        $repository->save(self::questionnaire(self::Q1, self::RDV1));
        $repository->save(self::questionnaire(self::Q2, self::RDV2));
        $closed = self::questionnaire(self::Q3, self::RDV3);
        $closed->close(ClosureReason::APPOINTMENT_DECLINED, self::utc('2026-10-28 09:00:00'));
        $repository->save($closed);

        $ours     = [self::Q1, self::Q2, self::Q3];
        $unlocked = static fn (): array => array_values(array_filter(
            array_map(static fn (PreVisitQuestionnaire $q): string => $q->id()->toString(), $repository->findUnlocked(100_000)),
            static fn (string $id): bool => \in_array($id, $ours, true),
        ));
        self::assertSame([self::Q1, self::Q2], $unlocked());

        // Read before the lock (no FOR UPDATE), saved after it: the lock stays
        $stale = $repository->findByAppointment(self::CLINIC, self::RDV1);
        \assert(null !== $stale);
        $repository->recordLock(self::CLINIC, self::RDV1, self::utc('2026-10-29 08:00:00'));
        $repository->recordLock(self::CLINIC, self::RDV1, self::utc('2026-10-30 08:00:00'));
        $repository->recordLock(self::CLINIC, self::RDV3, self::utc('2026-10-29 08:00:00'));
        $repository->recordLock(self::CLINIC2, self::RDV2, self::utc('2026-10-29 08:00:00'));
        $repository->recordLock(self::CLINIC, 'not-a-uuid', self::utc('2026-10-29 08:00:00'));
        $stale->eraseFreeText();
        $repository->save($stale);

        self::assertSame('2026-10-29 08:00:00', $repository->findByAppointment(self::CLINIC, self::RDV1)?->lockedAt()?->format('Y-m-d H:i:s'));
        self::assertNull($repository->findByAppointment(self::CLINIC, self::RDV3)?->lockedAt(), 'a closed questionnaire is left alone');
        self::assertSame([self::Q2], $unlocked());
    }

    private function repository(): DbalPreVisitQuestionnaireRepository
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return new DbalPreVisitQuestionnaireRepository($connection);
    }

    private static function questionnaire(string $id, string $appointment, string $clinic = self::CLINIC, string $client = self::CLIENT, string $user = self::USER, string $at = '2026-10-28 08:00:00'): PreVisitQuestionnaire
    {
        return PreVisitQuestionnaire::submit(
            PortalRequestId::fromString($id),
            $clinic,
            $client,
            $user,
            $appointment,
            1,
            QuestionnaireAnswers::of(['cough', 'itching'], true, 'Sirop', 'may_bite', 'Il tousse.'),
            new \DateTimeImmutable($at, new \DateTimeZone('UTC')),
        );
    }

    private static function utc(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }
}
