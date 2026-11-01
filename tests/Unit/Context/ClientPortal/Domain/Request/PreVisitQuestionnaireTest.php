<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Domain\Request;

use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;
use App\Context\ClientPortal\Domain\Exception\QuestionnaireLockedException;
use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\PortalRequestId;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaire;
use App\Context\ClientPortal\Domain\Request\QuestionnaireAnswers;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Context\ClientPortal\Domain\Request\Symptom;
use App\Context\ClientPortal\Domain\Request\Temperament;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as W;
use App\Tests\Unit\Context\ClientPortal\Support\Requests;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** AC 66, 232: the questionnaire's answers, version and lock. */
final class PreVisitQuestionnaireTest extends TestCase
{
    private const string ID          = '01960000-0000-7000-8000-0000000e0001';
    private const string APPOINTMENT = '01960000-0000-7000-8000-0000000b0001';
    private const string OTHER_USER  = '01960000-0000-7000-8000-0000000c0099';

    public function testAnswersAreTrimmedAndTheDetailsKeptOnlyWithATreatment(): void
    {
        $answers = QuestionnaireAnswers::of(['cough', 'vomiting', 'cough'], true, '  Sirop  ', 'may_bite', '  ');

        self::assertSame([[Symptom::COUGH, Symptom::VOMITING], true, 'Sirop', Temperament::MAY_BITE, null, true], [
            $answers->symptoms(), $answers->hasOngoingTreatment(), $answers->ongoingTreatmentDetails(), $answers->temperament(), $answers->notes(), $answers->mayBite(),
        ]);

        $none = QuestionnaireAnswers::of(['none'], false, 'Ignoré', 'calm', str_repeat('a', 1000));
        self::assertSame([[Symptom::NONE], null, false], [$none->symptoms(), $none->ongoingTreatmentDetails(), $none->mayBite()]);
    }

    /**
     * @return iterable<string, array{list<string>, ?string, string, ?string, string}>
     */
    public static function provideInvalidAnswersAreRefusedOnTheirPropertyCases(): iterable
    {
        yield 'none with another symptom' => [['none', 'cough'], null, 'calm', null, 'symptoms'];
        yield 'no symptom at all' => [[], null, 'calm', null, 'symptoms'];
        yield 'unknown symptom' => [['sneezing'], null, 'calm', null, 'symptoms'];
        yield 'unknown temperament' => [['none'], null, 'grumpy', null, 'temperament'];
        yield 'details over 500' => [['none'], str_repeat('a', 501), 'calm', null, 'ongoingTreatment.details'];
        yield 'notes over 1000' => [['none'], null, 'calm', str_repeat('a', 1001), 'notes'];
    }

    /**
     * @param list<string> $symptoms
     */
    #[DataProvider('provideInvalidAnswersAreRefusedOnTheirPropertyCases')]
    public function testInvalidAnswersAreRefusedOnTheirProperty(array $symptoms, ?string $details, string $temperament, ?string $notes, string $property): void
    {
        try {
            QuestionnaireAnswers::of($symptoms, true, $details, $temperament, $notes);
            self::fail('The answers should be refused.');
        } catch (InvalidPortalRequestException $e) {
            self::assertSame($property, $e->propertyPath);
        }
    }

    public function testOnlyVersionOneIsAccepted(): void
    {
        $this->expectExceptionObject(new InvalidPortalRequestException('version', 'Version de questionnaire non prise en charge : 2.'));

        PreVisitQuestionnaire::submit(PortalRequestId::fromString(self::ID), W::CLINIC, W::CLIENT, W::USER, self::APPOINTMENT, 2, Requests::answers(), Requests::utc('2026-10-28 08:00:00'));
    }

    public function testAnswersAreRewrittenUntilTheLock(): void
    {
        $questionnaire = Requests::questionnaire(self::ID, self::APPOINTMENT);
        self::assertSame([self::ID, W::CLINIC, W::CLIENT, W::USER, self::APPOINTMENT, 1, RequestStatus::SUBMITTED, true, true, null], [
            $questionnaire->id()->toString(), $questionnaire->clinicId(), $questionnaire->clientId(), $questionnaire->userId(), $questionnaire->appointmentId(),
            $questionnaire->version(), $questionnaire->status(), $questionnaire->isSubmitted(), $questionnaire->isEditable(), $questionnaire->lockedAt(),
        ]);

        $questionnaire->answer(1, self::OTHER_USER, Requests::answers('stressed'), Requests::utc('2026-10-28 09:00:00'));
        self::assertSame([self::OTHER_USER, Temperament::STRESSED, '2026-10-28 09:00:00', '2026-10-28 08:00:00'], [
            $questionnaire->userId(), $questionnaire->answers()->temperament(), $questionnaire->updatedAt()->format('Y-m-d H:i:s'), $questionnaire->submittedAt()->format('Y-m-d H:i:s'),
        ]);

        try {
            $questionnaire->answer(2, W::USER, Requests::answers(), Requests::utc('2026-10-28 09:30:00'));
            self::fail('Version 2 should be refused.');
        } catch (InvalidPortalRequestException $e) {
            self::assertSame('version', $e->propertyPath);
        }

        self::assertTrue($questionnaire->lock(Requests::utc('2026-10-28 10:00:00')));
        self::assertFalse($questionnaire->lock(Requests::utc('2026-10-28 11:00:00')));
        self::assertSame(['2026-10-28 10:00:00', false], [$questionnaire->lockedAt()?->format('Y-m-d H:i:s'), $questionnaire->isEditable()]);

        $this->expectExceptionObject(new QuestionnaireLockedException(self::APPOINTMENT));
        $questionnaire->answer(1, W::USER, Requests::answers(), Requests::utc('2026-10-28 12:00:00'));
    }

    public function testAClosedQuestionnaireIsNoLongerEditable(): void
    {
        $questionnaire = Requests::questionnaire(self::ID, self::APPOINTMENT);

        self::assertTrue($questionnaire->close(ClosureReason::APPOINTMENT_CANCELLED, Requests::utc('2026-10-28 10:00:00')));
        self::assertFalse($questionnaire->close(ClosureReason::ACCOUNT_DELETED, Requests::utc('2026-10-28 11:00:00')));
        self::assertSame([RequestStatus::CLOSED, ClosureReason::APPOINTMENT_CANCELLED, '2026-10-28 10:00:00', false, false], [
            $questionnaire->status(), $questionnaire->closedReason(), $questionnaire->closedAt()?->format('Y-m-d H:i:s'), $questionnaire->isSubmitted(), $questionnaire->isEditable(),
        ]);

        $this->expectException(QuestionnaireLockedException::class);
        $questionnaire->answer(1, W::USER, Requests::answers(), Requests::utc('2026-10-28 12:00:00'));
    }

    public function testAnAccountDeletionErasesTheTextsAndKeepsTheChoices(): void
    {
        $questionnaire = Requests::questionnaire(self::ID, self::APPOINTMENT);
        $questionnaire->eraseFreeText();
        $answers = $questionnaire->answers();

        self::assertSame([[Symptom::COUGH], true, null, Temperament::CALM, null], [$answers->symptoms(), $answers->hasOngoingTreatment(), $answers->ongoingTreatmentDetails(), $answers->temperament(), $answers->notes()]);
    }

    public function testAStoredQuestionnaireIsReconstitutedAsIs(): void
    {
        $answers       = QuestionnaireAnswers::reconstitute([Symptom::ITCHING], false, null, Temperament::CALM, 'Gratte');
        $questionnaire = PreVisitQuestionnaire::reconstitute(
            PortalRequestId::fromString(self::ID),
            W::CLINIC,
            W::CLIENT,
            W::USER,
            self::APPOINTMENT,
            1,
            $answers,
            RequestStatus::SUBMITTED,
            Requests::utc('2026-10-28 08:00:00'),
            Requests::utc('2026-10-28 09:00:00'),
            Requests::utc('2026-10-28 10:00:00'),
            null,
            null,
        );

        self::assertSame([$answers, '2026-10-28 10:00:00', false], [$questionnaire->answers(), $questionnaire->lockedAt()?->format('Y-m-d H:i:s'), $questionnaire->isEditable()]);
    }
}
