<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\Requests;

use App\Context\ClientPortal\Application\Command\SubmitQuestionnaire\SubmitQuestionnaire;
use App\Context\ClientPortal\Application\Command\SubmitQuestionnaire\SubmitQuestionnaireOutcome;
use App\Context\ClientPortal\Domain\Exception\AppointmentNotFoundException;
use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;
use App\Context\ClientPortal\Domain\Request\Symptom;
use App\Context\ClientPortal\Domain\Request\Temperament;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as P;
use App\Tests\Unit\Context\ClientPortal\Support\Requests;
use App\Tests\Unit\Context\ClientPortal\Support\RequestWorld as W;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** AC 66, 232, 233: who may answer, and until when. */
final class SubmitQuestionnaireHandlerTest extends TestCase
{
    public function testTheFirstAnswerCreatesTheQuestionnaireForTheClientOfTheAppointment(): void
    {
        $world = new W();
        $world->appointment();

        self::assertSame(SubmitQuestionnaireOutcome::SUBMITTED, $world->submitQuestionnaire()($this->command()));

        $questionnaire = $world->questionnaires->findByAppointment(P::CLINIC, W::APPOINTMENT);
        self::assertNotNull($questionnaire);
        self::assertSame([P::CLIENT, P::USER, 1, [Symptom::COUGH], 'Sirop', Temperament::MAY_BITE, 'Il tousse.', '2026-10-29 09:00:00'], [
            $questionnaire->clientId(), $questionnaire->userId(), $questionnaire->version(), $questionnaire->answers()->symptoms(),
            $questionnaire->answers()->ongoingTreatmentDetails(), $questionnaire->answers()->temperament(), $questionnaire->answers()->notes(),
            $questionnaire->submittedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function testAnAppointmentOfAVisiblePetOfAnotherClientIsAnsweredForThePetsOwner(): void
    {
        $world = new W();
        $world->appointment(ownerId: null);
        $world->pets->documents->clock->advance('+1 hour');
        $world->questionnaires->save(Requests::questionnaire('01960000-0000-7000-8000-0000000e0001', W::APPOINTMENT));

        self::assertSame(SubmitQuestionnaireOutcome::SUBMITTED, $world->submitQuestionnaire()($this->command(symptoms: ['none'], temperament: 'calm')));

        $questionnaire = $world->questionnaires->findByAppointment(P::CLINIC, W::APPOINTMENT);
        self::assertSame(['01960000-0000-7000-8000-0000000e0001', [Symptom::NONE], '2026-10-29 10:00:00'], [
            $questionnaire?->id()->toString(), $questionnaire?->answers()->symptoms(), $questionnaire?->updatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return iterable<string, array{?string, ?string, string}>
     */
    public static function provideAnAppointmentThatIsNotTheAccountsOrNoLongerTakesPlaceIsNotFoundCases(): iterable
    {
        yield 'another client, another pet' => [P::CLIENT2, P::PET3, 'confirmed'];
        yield 'no client, no pet' => [null, null, 'confirmed'];
        yield 'cancelled by the client' => [P::CLIENT, P::PET, 'cancelled_by_client'];
        yield 'cancelled by the clinic' => [P::CLIENT, P::PET, 'cancelled_by_clinic'];
        yield 'declined' => [P::CLIENT, P::PET, 'declined'];
    }

    #[DataProvider('provideAnAppointmentThatIsNotTheAccountsOrNoLongerTakesPlaceIsNotFoundCases')]
    public function testAnAppointmentThatIsNotTheAccountsOrNoLongerTakesPlaceIsNotFound(?string $ownerId, ?string $animalId, string $status): void
    {
        $world = new W();
        $world->appointment(ownerId: $ownerId, animalId: $animalId, status: $status, occupies: 'confirmed' === $status);

        $this->expectException(AppointmentNotFoundException::class);
        $world->submitQuestionnaire()($this->command());
    }

    public function testAnUnknownAppointmentIsNotFound(): void
    {
        $this->expectException(AppointmentNotFoundException::class);
        new W()->submitQuestionnaire()($this->command());
    }

    public function testVersionTwoAndInvalidAnswersAreRefusedBeforeAnyWrite(): void
    {
        $world = new W();
        $world->appointment();

        foreach ([[2, ['cough'], 'version'], [1, ['none', 'cough'], 'symptoms']] as [$version, $symptoms, $property]) {
            try {
                $world->submitQuestionnaire()($this->command(version: $version, symptoms: $symptoms));
                self::fail('The answers should be refused.');
            } catch (InvalidPortalRequestException $e) {
                self::assertSame($property, $e->propertyPath);
            }
        }

        self::assertSame(0, $world->questionnaires->saves);
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function provideAFrozenAppointmentLocksTheQuestionnaireForGoodCases(): iterable
    {
        yield 'started' => ['2026-10-29 09:00:00', true, false];
        yield 'no longer holding its slot' => ['2026-11-05 09:00:00', false, false];
        yield 'a consultation began from it' => ['2026-11-05 09:00:00', true, true];
    }

    #[DataProvider('provideAFrozenAppointmentLocksTheQuestionnaireForGoodCases')]
    public function testAFrozenAppointmentLocksTheQuestionnaireForGood(string $startsAt, bool $occupies, bool $consulted): void
    {
        $world = new W();
        $world->appointment(startsAt: $startsAt, status: $occupies ? 'confirmed' : 'completed', occupies: $occupies);
        $world->fakes->started = $consulted ? [W::APPOINTMENT] : [];
        $world->questionnaires->save(Requests::questionnaire('01960000-0000-7000-8000-0000000e0001', W::APPOINTMENT));

        self::assertSame(SubmitQuestionnaireOutcome::LOCKED, $world->submitQuestionnaire()($this->command(notes: 'Changé')));

        $questionnaire = $world->questionnaires->findByAppointment(P::CLINIC, W::APPOINTMENT);
        self::assertSame(['2026-10-29 09:00:00', 'Il tousse la nuit.'], [$questionnaire?->lockedAt()?->format('Y-m-d H:i:s'), $questionnaire?->answers()->notes()]);

        // A reschedule to a later time never reopens it (AC 232)
        $world->pets->fakes->appointments = [];
        $world->appointment(startsAt: '2026-12-01 09:00:00');
        $world->fakes->started = [];

        self::assertSame(SubmitQuestionnaireOutcome::LOCKED, $world->submitQuestionnaire()($this->command()));
        self::assertSame(2, $world->questionnaires->saves, 'the lock is written once');
    }

    public function testAFrozenAppointmentWithoutQuestionnaireWritesNothing(): void
    {
        $world = new W();
        $world->appointment(startsAt: '2026-10-29 08:00:00');

        self::assertSame(SubmitQuestionnaireOutcome::LOCKED, $world->submitQuestionnaire()($this->command()));
        self::assertSame(0, $world->questionnaires->saves);
    }

    /**
     * @param list<string> $symptoms
     */
    private function command(int $version = 1, array $symptoms = ['cough'], string $temperament = 'may_bite', ?string $notes = 'Il tousse.'): SubmitQuestionnaire
    {
        return new SubmitQuestionnaire(P::USER, P::CLINIC, W::APPOINTMENT, $version, $symptoms, true, 'Sirop', $temperament, $notes);
    }
}
