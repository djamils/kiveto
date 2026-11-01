<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic\Scheduling;

use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Query\ListQuestionnaireSummaries\ListQuestionnaireSummaries;
use App\Context\ClientPortal\Application\Query\ListQuestionnaireSummaries\ListQuestionnaireSummariesHandler;
use App\Context\ClientPortal\Domain\Request\PortalRequestId;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaire;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaireRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\QuestionnaireAnswers;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Domain\Time\ClockInterface;
use App\Tests\Shared\Time\FrozenClock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * The Portal pre-visit questionnaire on the agenda (AC 241, AC 239): a badge
 * on each card that has one, read in one batch for the range, and the
 * answers loaded by the popover from the questionnaire endpoint — the
 * owner's text handed as JSON data, never as markup.
 */
final class AgendaQuestionnaireTest extends WebTestCase
{
    use Factories;

    private const string CLINIC = '01960000-0000-7000-8000-0000000e7c01';
    private const string OTHER  = '01960000-0000-7000-8000-0000000e7c02';
    private const string BITES  = '01960000-0000-7000-8000-0000000e7a01';
    private const string CALM   = '01960000-0000-7000-8000-0000000e7a02';
    private const string NONE   = '01960000-0000-7000-8000-0000000e7a03';
    private const string HOST   = 'http://clinic.kiveto.local';
    private const string XSS    = '<img src=x onerror=alert(1)>';

    private KernelBrowser $browser;

    /** @var object{calls: int} */
    private object $summaries;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        $now       = new \DateTimeImmutable('2026-11-02 10:00:00', new \DateTimeZone('UTC'));
        $container = self::getContainer();
        $container->set(ClockInterface::class, new FrozenClock($now));

        // Counts the batches the agenda asks for.
        $questionnaires = $container->get(PreVisitQuestionnaireRepositoryInterface::class);
        $clinics        = $container->get(ClinicProfileInterface::class);
        \assert($questionnaires instanceof PreVisitQuestionnaireRepositoryInterface && $clinics instanceof ClinicProfileInterface);
        $real            = new ListQuestionnaireSummariesHandler($questionnaires, $clinics);
        $this->summaries = new class($real) {
            public int $calls = 0;

            public function __construct(private readonly ListQuestionnaireSummariesHandler $real)
            {
            }

            /**
             * @return list<mixed>
             */
            public function __invoke(ListQuestionnaireSummaries $query): array
            {
                ++$this->calls;

                return ($this->real)($query);
            }
        };
        $container->set(ListQuestionnaireSummariesHandler::class, $this->summaries);

        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique des Tilleuls')->create(['timeZone' => 'Europe/Paris']);
        ClinicEntityFactory::new()->withId(self::OTHER)->withName('Clinique du Port')->create(['timeZone' => 'Europe/Paris']);
        $vet   = ClinicUserFactory::new()->withEmail('agenda.vet@example.com')->withPlainPassword('mot-de-passe')->create();
        $vetId = $vet->getId()->toRfc4122();
        ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($vetId)->asVeterinary()->asEmployee()->create();

        foreach ([self::BITES => '13:00', self::CALM => '14:00', self::NONE => '15:00'] as $id => $time) {
            AppointmentEntityFactory::new()->withId($id)->withClinicId(self::CLINIC)->withPractitionerUserId($vetId)->withReason('Consultation')
                ->startingAt(new \DateTimeImmutable('2026-11-02 ' . $time, new \DateTimeZone('UTC')), 30)->create()
            ;
        }

        $this->questionnaire(self::BITES, 'may_bite', self::XSS, $now->modify('-1 hour'));
        $this->questionnaire(self::CALM, 'calm', null, $now->modify('-2 hours'));

        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"email":"agenda.vet@example.com","password":"mot-de-passe"}');
        self::assertResponseIsSuccessful();
    }

    public function testEachCardWithAQuestionnaireCarriesItsBadgeFromOneBatch(): void
    {
        $crawler = $this->browser->request('GET', self::HOST . '/scheduling/agenda?date=2026-11-02&view=day');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString(self::XSS, (string) $this->browser->getResponse()->getContent());

        $data = json_decode($crawler->filter('#agenda-data')->text(), true, flags: \JSON_THROW_ON_ERROR);
        \assert(\is_array($data) && \is_array($data['range']) && \is_array($data['range']['appointments']));
        self::assertSame([
            self::BITES => ['at' => '02/11 10:00', 'bite' => true],
            self::CALM  => ['at' => '02/11 09:00', 'bite' => false],
            self::NONE  => null,
        ], self::badges($data['range']['appointments']));
        self::assertSame(1, $this->summaries->calls, 'one batch for the range');
        self::assertIsArray($data['urls']);
        self::assertSame('/clinic/agenda/appointments/__ID__/questionnaire', $data['urls']['questionnaire']);

        $this->browser->request('GET', self::HOST . '/scheduling/agenda/range?from=2026-11-02&to=2026-11-08');
        self::assertResponseIsSuccessful();
        $range = json_decode((string) $this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        \assert(\is_array($range) && \is_array($range['appointments']));
        self::assertSame(['at' => '02/11 10:00', 'bite' => true], self::badges($range['appointments'])[self::BITES]);
        self::assertSame(2, $this->summaries->calls);
    }

    public function testThePopoverLoadsTheAnswersAsData(): void
    {
        $this->browser->request('GET', self::HOST . '/clinic/agenda/appointments/' . self::BITES . '/questionnaire');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $answer = json_decode((string) $this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame([
            'updatedAt'               => '02/11/2026 à 10:00',
            'mayBite'                 => true,
            'symptoms'                => ['Toux', 'Vomissements'],
            'ongoingTreatment'        => true,
            'ongoingTreatmentDetails' => 'Méloxicam',
            'temperament'             => 'Peut mordre',
            'notes'                   => self::XSS,
        ], $answer);
    }

    public function testNoQuestionnaireOrAnotherClinicsAppointmentIsNotFound(): void
    {
        $this->browser->request('GET', self::HOST . '/clinic/agenda/appointments/' . self::NONE . '/questionnaire');
        self::assertResponseStatusCodeSame(404);

        $foreign = Uuid::v7()->toRfc4122();
        $this->questionnaire($foreign, 'calm', 'Ailleurs', new \DateTimeImmutable('2026-11-01'), self::OTHER);
        $this->browser->request('GET', self::HOST . '/clinic/agenda/appointments/' . $foreign . '/questionnaire');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @param array<mixed> $appointments
     *
     * @return array<string, mixed>
     */
    private static function badges(array $appointments): array
    {
        $badges = [];

        foreach ($appointments as $appointment) {
            \assert(\is_array($appointment) && \is_string($appointment['id']));
            $badges[$appointment['id']] = $appointment['qn'];
        }

        return $badges;
    }

    private function questionnaire(string $appointmentId, string $temperament, ?string $notes, \DateTimeImmutable $at, string $clinicId = self::CLINIC): void
    {
        $repository = self::getContainer()->get(PreVisitQuestionnaireRepositoryInterface::class);
        \assert($repository instanceof PreVisitQuestionnaireRepositoryInterface);

        $repository->save(PreVisitQuestionnaire::submit(
            PortalRequestId::fromString(Uuid::v7()->toRfc4122()),
            $clinicId,
            Uuid::v7()->toRfc4122(),
            Uuid::v7()->toRfc4122(),
            $appointmentId,
            PreVisitQuestionnaire::VERSION,
            QuestionnaireAnswers::of(['cough', 'vomiting'], 'calm' !== $temperament, 'calm' !== $temperament ? 'Méloxicam' : null, $temperament, $notes),
            $at,
        ));
    }
}
