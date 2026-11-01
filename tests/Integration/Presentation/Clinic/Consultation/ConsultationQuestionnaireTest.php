<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic\Consultation;

use App\Context\ClientPortal\Domain\Request\PortalRequestId;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaire;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaireRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\QuestionnaireAnswers;
use App\Context\Consultation\Domain\ValueObject\ConsultationStatus;
use App\Fixtures\Context\Admission\Factory\AdmissionEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Consultation\Factory\ConsultationEntityFactory;
use App\Fixtures\Context\Patient\Factory\PatientEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * The owner's pre-visit questionnaire in the consultation cockpit (AC 70,
 * AC 239): a consultation started from an appointment carries the answers,
 * their `updatedAt` and the « peut mordre » flag; the owner's text is JSON
 * data the page module sets as text. Without appointment, nothing.
 */
final class ConsultationQuestionnaireTest extends WebTestCase
{
    use Factories;

    private const string CLINIC       = '01960000-0000-7000-8000-0000000f7c01';
    private const string FROM_BOOKING = '01960000-0000-7000-8000-0000000f7a01';
    private const string WALK_IN      = '01960000-0000-7000-8000-0000000f7a02';
    private const string APPOINTMENT  = '01960000-0000-7000-8000-0000000f7b01';
    private const string HOST         = 'http://clinic.kiveto.local';
    private const string XSS          = '<img src=x onerror=alert(1)>';

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique des Tilleuls')->create(['timeZone' => 'Europe/Paris']);
        $vet   = ClinicUserFactory::new()->withEmail('cockpit.vet@example.com')->withPlainPassword('mot-de-passe')->create();
        $vetId = $vet->getId()->toRfc4122();
        ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($vetId)->asVeterinary()->asEmployee()->create();

        $this->consultation(self::FROM_BOOKING, self::APPOINTMENT, $vetId);
        $this->consultation(self::WALK_IN, null, $vetId);

        $repository = self::getContainer()->get(PreVisitQuestionnaireRepositoryInterface::class);
        \assert($repository instanceof PreVisitQuestionnaireRepositoryInterface);
        $repository->save(PreVisitQuestionnaire::submit(
            PortalRequestId::fromString(Uuid::v7()->toRfc4122()),
            self::CLINIC,
            Uuid::v7()->toRfc4122(),
            Uuid::v7()->toRfc4122(),
            self::APPOINTMENT,
            PreVisitQuestionnaire::VERSION,
            QuestionnaireAnswers::of(['diarrhea'], false, null, 'may_bite', self::XSS),
            new \DateTimeImmutable('2026-10-30 08:15:00', new \DateTimeZone('UTC')),
        ));

        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"email":"cockpit.vet@example.com","password":"mot-de-passe"}');
        self::assertResponseIsSuccessful();
    }

    public function testAConsultationFromABookingShowsTheQuestionnaire(): void
    {
        $crawler = $this->browser->request('GET', self::HOST . '/clinic/consultations/' . self::FROM_BOOKING);

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString(self::XSS, (string) $this->browser->getResponse()->getContent());
        self::assertCount(1, $crawler->filter('#qnBox[hidden]'), 'drawn by the page module');
        self::assertSame([
            'updatedAt'               => '30/10/2026 à 09:15',
            'mayBite'                 => true,
            'symptoms'                => ['Diarrhée'],
            'ongoingTreatment'        => false,
            'ongoingTreatmentDetails' => null,
            'temperament'             => 'Peut mordre',
            'notes'                   => self::XSS,
        ], $this->hydration($crawler->filter('#consultation-data')->text())['questionnaire']);
    }

    public function testAWalkInHasNoQuestionnaire(): void
    {
        $crawler = $this->browser->request('GET', self::HOST . '/clinic/consultations/' . self::WALK_IN);

        self::assertResponseIsSuccessful();
        self::assertNull($this->hydration($crawler->filter('#consultation-data')->text())['questionnaire']);
    }

    /**
     * @return array<mixed>
     */
    private function hydration(string $json): array
    {
        $data = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        \assert(\is_array($data));
        self::assertArrayHasKey('questionnaire', $data);

        return $data;
    }

    private function consultation(string $id, ?string $appointmentId, string $vetId): void
    {
        $patientId   = Uuid::v7()->toRfc4122();
        $admissionId = Uuid::v7()->toRfc4122();
        PatientEntityFactory::new()->withId($patientId)->withClinicId(self::CLINIC)->provisional('Chien noir')->create();
        AdmissionEntityFactory::new()->withId($admissionId)->withClinicId(self::CLINIC)->withPatientId($patientId)->create();

        $utc = new \DateTimeZone('UTC');
        ConsultationEntityFactory::new()
            ->withId($id)
            ->withClinicId(self::CLINIC)
            ->withAppointmentId($appointmentId)
            ->withAdmissionId($admissionId)
            ->withPatientId($patientId)
            ->withPractitionerUserId($vetId)
            ->withStatus(ConsultationStatus::OPEN)
            ->create([
                'startedAtUtc' => new \DateTimeImmutable('2026-10-30 10:00', $utc),
                'createdAtUtc' => new \DateTimeImmutable('2026-10-30 10:00', $utc),
            ])
        ;
    }
}
