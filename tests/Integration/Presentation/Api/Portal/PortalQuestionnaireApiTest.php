<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Fixtures\Context\Consultation\Factory\ConsultationEntityFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;

/**
 * Slice 6: the pre-visit questionnaire (AC 66, 232, 233, 244). Today is
 * Monday 2 November 2026, 11:00 in Paris; the appointment is on Wednesday.
 */
final class PortalQuestionnaireApiTest extends RequestsApiTestCase
{
    private const string WEDNESDAY_9_UTC = '2026-11-04 08:00:00';

    private string $rex;

    private string $appointment;

    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.163';
        parent::setUp();

        $this->rex         = $this->pet('Rex');
        $this->appointment = $this->appointment($this->rex, self::WEDNESDAY_9_UTC);
    }

    public function testBeforeAnyAnswerTheQuestionnaireIsNotStarted(): void
    {
        self::assertSame(
            ['version' => 1, 'status' => 'not_started', 'answers' => null, 'updatedAt' => null, 'editable' => true],
            self::json($this->authGet($this->path()), 200),
        );
        self::assertSame('not_started', self::json($this->authGet(self::clinicPath('/appointments/' . $this->appointment)), 200)['questionnaireStatus']);
    }

    public function testSubmittedAnswersAreReturnedAndShownInTheAppointmentViews(): void
    {
        $answer = self::json($this->put($this->path(), self::body()), 200);

        self::assertSame([
            'version' => 1,
            'status'  => 'submitted',
            'answers' => [
                'symptoms'         => ['cough', 'itching'],
                'ongoingTreatment' => ['has' => true, 'details' => 'Meloxidyl depuis lundi'],
                'temperament'      => 'may_bite',
                'notes'            => 'Il a peur des blouses blanches.',
            ],
            'updatedAt' => '2026-11-02T11:00:00+01:00',
            'editable'  => true,
        ], $answer);
        self::assertSame($answer, self::json($this->authGet($this->path()), 200));
        self::assertSame('submitted', self::json($this->authGet(self::clinicPath('/appointments/' . $this->appointment)), 200)['questionnaireStatus']);

        $listed = array_column(self::items(self::json($this->authGet(self::clinicPath('/appointments')), 200)), 'questionnaireStatus', 'id');
        self::assertSame('submitted', $listed[$this->appointment] ?? null);
    }

    public function testAnotherPutReplacesTheAnswers(): void
    {
        $this->put($this->path(), self::body());
        $this->travelTo('2026-11-02 12:00:00');

        $answer = self::json($this->put($this->path(), self::body(symptoms: ['none'], treatment: ['has' => false], temperament: 'calm', notes: null)), 200);

        self::assertSame(['symptoms' => ['none'], 'ongoingTreatment' => ['has' => false, 'details' => null], 'temperament' => 'calm', 'notes' => null], $answer['answers']);
        self::assertSame('2026-11-02T13:00:00+01:00', $answer['updatedAt']);
        self::assertSame(1, $this->number('SELECT COUNT(*) FROM client_portal__pre_visit_questionnaires WHERE appointment_id = UUID_TO_BIN(?)', [$this->appointment]));
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('provideAnInvalidAnswerIsAViolationOnItsPropertyCases')]
    public function testAnInvalidAnswerIsAViolationOnItsProperty(array $body, string $path): void
    {
        $problem = self::json($this->put($this->path(), $body), 422);

        self::assertSame('validation_failed', $problem['type']);
        self::assertSame($path, self::firstViolationPath($problem));
        self::assertSame('not_started', self::json($this->authGet($this->path()), 200)['status']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideAnInvalidAnswerIsAViolationOnItsPropertyCases(): iterable
    {
        yield 'none with another symptom' => [self::body(symptoms: ['none', 'cough']), 'symptoms'];
        yield 'no symptom' => [self::body(symptoms: []), 'symptoms'];
        yield 'unknown symptom' => [self::body(symptoms: ['fever']), 'symptoms[0]'];
        yield 'version 2' => [self::body(version: 2), 'version'];
        yield 'unknown temperament' => [self::body(temperament: 'grumpy'), 'temperament'];
        yield 'long treatment details' => [self::body(treatment: ['has' => true, 'details' => str_repeat('a', 501)]), 'ongoingTreatment.details'];
        yield 'treatment without has' => [self::body(treatment: ['details' => 'Meloxidyl']), 'ongoingTreatment.has'];
        yield 'long notes' => [self::body(notes: str_repeat('a', 1001)), 'notes'];
    }

    public function testACancelledOrDeclinedAppointmentHasNoQuestionnaire(): void
    {
        $cancelled = $this->appointment($this->rex, '2026-11-05 08:00:00', AppointmentStatus::CANCELLED, cancelledBy: CancellationActor::CLIENT);
        $declined  = $this->appointment($this->rex, '2026-11-05 09:00:00', AppointmentStatus::DECLINED);

        foreach ([$cancelled, $declined] as $id) {
            self::assertSame('not_found', self::problemType($this->authGet($this->path($id)), 404));
            self::assertSame('not_found', self::problemType($this->put($this->path($id), self::body()), 404));
        }
    }

    public function testAnotherClientsUnknownOrForeignClinicsAppointmentIsNotFound(): void
    {
        $foreign = $this->appointment($this->pet('Nala', self::OTHER_CLIENT), '2026-11-05 08:00:00', clientId: self::OTHER_CLIENT);

        foreach ([$foreign, Uuid::v7()->toRfc4122()] as $id) {
            self::assertSame('not_found', self::problemType($this->authGet($this->path($id)), 404));
            self::assertSame('not_found', self::problemType($this->put($this->path($id), self::body()), 404));
        }

        self::assertSame('not_found', self::problemType($this->authGet('/clinics/' . self::OTHER_CLINIC . '/appointments/' . $this->appointment . '/questionnaire'), 404));
        self::assertSame('not_found', self::problemType($this->put('/clinics/' . self::OTHER_CLINIC . '/appointments/' . $this->appointment . '/questionnaire', self::body()), 404));
    }

    public function testAConsultationStartedFromTheAppointmentLocksIt(): void
    {
        ConsultationEntityFactory::new()->withClinicId(self::CLINIC)->withAppointmentId($this->appointment)->create();

        self::assertSame('questionnaire_locked', self::problemType($this->put($this->path(), self::body()), 422));
        self::assertFalse(self::json($this->authGet($this->path()), 200)['editable']);
    }

    public function testAStartedAppointmentLocksItAndARescheduleDoesNotReopenIt(): void
    {
        self::json($this->put($this->path(), self::body()), 200);
        $this->travelTo('2026-11-04 08:05:00');

        self::assertSame('questionnaire_locked', self::problemType($this->put($this->path(), self::body(temperament: 'calm')), 422));

        $this->connection()->executeStatement('UPDATE scheduling__appointments SET starts_at_utc = ? WHERE id = UUID_TO_BIN(?)', ['2026-11-10 08:00:00', $this->appointment]);

        self::assertSame('questionnaire_locked', self::problemType($this->put($this->path(), self::body(temperament: 'calm')), 422));
        $questionnaire = self::json($this->authGet($this->path()), 200);
        self::assertSame(['submitted', false], [$questionnaire['status'], $questionnaire['editable']]);
        $answers = $questionnaire['answers'] ?? null;
        self::assertIsArray($answers);
        self::assertSame('may_bite', $answers['temperament'], 'the answers are those sent before the lock');
    }

    protected function phone(): string
    {
        return '+33615000603';
    }

    /**
     * @param list<string>         $symptoms
     * @param array<string, mixed> $treatment
     *
     * @return array<string, mixed>
     */
    private static function body(
        array $symptoms = ['cough', 'itching'],
        array $treatment = ['has' => true, 'details' => 'Meloxidyl depuis lundi'],
        string $temperament = 'may_bite',
        ?string $notes = 'Il a peur des blouses blanches.',
        int $version = 1,
    ): array {
        return ['version' => $version, 'symptoms' => $symptoms, 'ongoingTreatment' => $treatment, 'temperament' => $temperament, 'notes' => $notes];
    }

    private function path(?string $appointmentId = null): string
    {
        return self::clinicPath('/appointments/' . ($appointmentId ?? $this->appointment) . '/questionnaire');
    }
}
