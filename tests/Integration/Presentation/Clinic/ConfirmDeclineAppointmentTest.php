<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic;

use App\Context\Clinic\Application\Query\Staff\ListPermissionsForUserInClinic\ListPermissionsForUserInClinic;
use App\Context\Clinic\Application\Query\Staff\ListPermissionsForUserInClinic\ListPermissionsForUserInClinicHandler;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Scheduling\Application\Command\ConfirmAppointment\ConfirmAppointment;
use App\Context\Scheduling\Application\Command\ConfirmAppointment\ConfirmAppointmentHandler;
use App\Context\Scheduling\Application\Command\DeclineAppointment\DeclineAppointment;
use App\Context\Scheduling\Application\Command\DeclineAppointment\DeclineAppointmentHandler;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\AppointmentItem;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\GetAgendaForClinicDateRange;
use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Tests\Shared\Time\FrozenClock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * « Confirmer » / « Refuser » an online request (AC 144, AC 145, AC 141):
 * from the agenda (JSON) and from the dashboard (form, back with a flash);
 * a request already handled or past its expiry answers its French reason,
 * never a 500; a role without `manage_appointments` is refused; « Non venu »
 * is refused on a pending request.
 */
final class ConfirmDeclineAppointmentTest extends WebTestCase
{
    use Factories;

    private const string CLINIC  = '01960000-0000-7000-8000-0000000b6c01';
    private const string OTHER   = '01960000-0000-7000-8000-0000000b6c02';
    private const string PENDING = '01960000-0000-7000-8000-0000000b6a01';
    private const string EXPIRED = '01960000-0000-7000-8000-0000000b6a02';
    private const string FOREIGN = '01960000-0000-7000-8000-0000000b6a03';
    private const string STARTED = '01960000-0000-7000-8000-0000000b6a04';
    private const string PLANNED = '01960000-0000-7000-8000-0000000b6a05';
    private const string HOST    = 'http://clinic.kiveto.local';

    private KernelBrowser $browser;
    private \DateTimeImmutable $now;
    private string $vetId = '';
    private string $token = '';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        $this->now = new \DateTimeImmutable('today 10:00', new \DateTimeZone('Europe/Paris'));
        self::getContainer()->set(ClockInterface::class, new FrozenClock($this->now));

        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique des Tilleuls')->create(['timeZone' => 'Europe/Paris']);
        ClinicEntityFactory::new()->withId(self::OTHER)->withName('Clinique du Port')->create(['timeZone' => 'Europe/Paris']);
        $user        = ClinicUserFactory::new()->withEmail('decide.vet@example.com')->withPlainPassword('mot-de-passe')->create();
        $this->vetId = $user->getId()->toRfc4122();
        ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($this->vetId)->asVeterinary()->asEmployee()->create();

        // Tomorrow at 14:00, waiting until tomorrow 08:00; the other one expired at 09:00 today.
        $this->request(self::PENDING, self::CLINIC, '+1 day 14:00', '+1 day 08:00');
        $this->request(self::EXPIRED, self::CLINIC, '+1 day 16:00', '09:00');
        $this->request(self::FOREIGN, self::OTHER, '+1 day 15:00', '+1 day 08:00');
        // Left unanswered although its time has come: « Non venu » is still refused.
        $this->request(self::STARTED, self::CLINIC, '08:00', '11:00');
    }

    public function testTheAgendaConfirmsARequestAndASecondDecisionIsRefusedAsAlreadyHandled(): void
    {
        $this->openAgenda();

        $answer = $this->json('confirm', self::PENDING);
        self::assertResponseIsSuccessful();
        self::assertSame(['success' => true, 'appointmentId' => self::PENDING, 'message' => 'RDV confirmé.'], $answer);
        self::assertSame('PLANNED', $this->statusOf(self::PENDING));

        $answer = $this->json('decline', self::PENDING);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('Ce RDV a déjà été traité.', $answer['message'] ?? null);
        self::assertSame('APPOINTMENT_ALREADY_HANDLED', $answer['errorCode'] ?? null);
        self::assertSame('PLANNED', $this->statusOf(self::PENDING));
    }

    public function testTheDashboardDeclinesARequestAndComesBackWithAFlash(): void
    {
        $this->openAgenda();

        $this->browser->request('POST', self::HOST . '/scheduling/appointments/' . self::PENDING . '/decline', ['_token' => $this->token]);
        self::assertResponseRedirects('/dashboard');
        self::assertSame('DECLINED', $this->statusOf(self::PENDING));

        $this->browser->followRedirect();
        self::assertStringContainsString('Demande refusée — le client est prévenu.', (string) $this->browser->getResponse()->getContent());

        // Confirming it afterwards loses, with its reason.
        $this->browser->request('POST', self::HOST . '/scheduling/appointments/' . self::PENDING . '/confirm', ['_token' => $this->token]);
        self::assertResponseRedirects('/dashboard');
        $this->browser->followRedirect();
        self::assertStringContainsString('Ce RDV a déjà été traité.', (string) $this->browser->getResponse()->getContent());
    }

    public function testARequestPastItsExpiryCannotBeConfirmed(): void
    {
        $this->openAgenda();

        $answer = $this->json('confirm', self::EXPIRED);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('Demande expirée.', $answer['message'] ?? null);
        self::assertSame('PENDING_CLINIC_CONFIRMATION', $this->statusOf(self::EXPIRED));

        $this->browser->request('POST', self::HOST . '/scheduling/appointments/' . self::EXPIRED . '/confirm', ['_token' => $this->token]);
        self::assertResponseRedirects('/dashboard');
        $this->browser->followRedirect();
        self::assertStringContainsString('Demande expirée.', (string) $this->browser->getResponse()->getContent());
    }

    public function testARoleWithoutManageAppointmentsIsRefused(): void
    {
        // A role that would not hold `manage_appointments`: Clinic answers the
        // member's permissions without it, every other one kept.
        $container = self::getContainer();
        $real      = new ListPermissionsForUserInClinicHandler($this->service(ClinicMembershipRepositoryInterface::class), $this->service(ClockInterface::class));
        $container->set(ListPermissionsForUserInClinicHandler::class, new readonly class($real) {
            public function __construct(private ListPermissionsForUserInClinicHandler $real)
            {
            }

            /**
             * @return list<string>
             */
            public function __invoke(ListPermissionsForUserInClinic $query): array
            {
                return array_values(array_diff(($this->real)($query), [ClinicPermissionGuard::MANAGE_APPOINTMENTS]));
            }
        });

        $this->openAgenda();

        $answer = $this->json('confirm', self::PENDING);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('FORBIDDEN', $answer['errorCode'] ?? null);

        $this->json('decline', self::PENDING);
        self::assertResponseStatusCodeSame(403);

        $this->browser->request('POST', self::HOST . '/scheduling/appointments/' . self::PENDING . '/decline', ['_token' => $this->token]);
        self::assertResponseRedirects('/dashboard');

        // S4A-01: « Annuler » a pending request is a decline: refused too
        $answer = $this->json('cancel', self::PENDING);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('FORBIDDEN', $answer['errorCode'] ?? null);

        self::assertSame('PENDING_CLINIC_CONFIRMATION', $this->statusOf(self::PENDING));

        // A planned appointment is still cancelled without that permission
        AppointmentEntityFactory::new()->withId(self::PLANNED)->withClinicId(self::CLINIC)->withPractitionerUserId($this->vetId)
            ->startingAt($this->now->modify('+1 day 11:00')->setTimezone(new \DateTimeZone('UTC')), 30)->create()
        ;
        $this->json('cancel', self::PLANNED);
        self::assertResponseIsSuccessful();
        self::assertSame('CANCELLED', $this->statusOf(self::PLANNED));
    }

    public function testAnyOtherRefusalIsAConflictNeverA500(): void
    {
        // S4A-06: the practitioner's agenda contended under the lock
        self::getContainer()->set(ConfirmAppointmentHandler::class, new class {
            public function __invoke(ConfirmAppointment $command): never
            {
                throw SlotTakenException::contended(new \RuntimeException('Lock wait timeout'));
            }
        });
        self::getContainer()->set(DeclineAppointmentHandler::class, new class {
            public function __invoke(DeclineAppointment $command): never
            {
                throw new \DomainException('Cannot decline.');
            }
        });
        $this->openAgenda();

        $answer = $this->json('confirm', self::PENDING);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(['L’agenda est en cours de modification : réessayez dans un instant.', 'APPOINTMENT_CONFLICT'], [$answer['message'] ?? null, $answer['errorCode'] ?? null]);

        $answer = $this->json('decline', self::PENDING);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('Cette demande n’a pas pu être traitée : rechargez la page.', $answer['message'] ?? null);
    }

    public function testAnotherClinicsRequestIsNotFoundAndAForgedTokenIsRefused(): void
    {
        $this->openAgenda();

        $this->json('confirm', self::FOREIGN);
        self::assertResponseStatusCodeSame(404);

        // S4A-06: no appointment id at all is not found either
        $this->json('confirm', 'not-a-uuid');
        self::assertResponseStatusCodeSame(404);

        $this->browser->request('POST', self::HOST . '/scheduling/appointments/' . self::PENDING . '/confirm', ['_token' => 'forged'], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('PENDING_CLINIC_CONFIRMATION', $this->statusOf(self::PENDING));
    }

    public function testAPendingRequestCannotBeMarkedNoShow(): void
    {
        $this->openAgenda();

        $answer = $this->json('no-show', self::STARTED);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['Un rendez-vous à confirmer ne peut pas être marqué « Non venu ».'], \is_array($answer['errors'] ?? null) ? $answer['errors']['global'] ?? null : null);
        self::assertSame('PENDING_CLINIC_CONFIRMATION', $this->statusOf(self::STARTED));
    }

    private function request(string $id, string $clinicId, string $start, string $expires): void
    {
        $utc = new \DateTimeZone('UTC');

        AppointmentEntityFactory::new()
            ->withId($id)
            ->withClinicId($clinicId)
            ->withPractitionerUserId($this->vetId)
            // A known client's request: a new client's is resolved in « Demandes Portal » (S7d)
            ->withOwnerAndAnimal('01960000-0000-7000-8000-0000000b6d01', null)
            ->withReason('Consultation')
            ->startingAt($this->now->modify($start)->setTimezone($utc), 30)
            ->pending($this->now->modify($expires)->setTimezone($utc))
            ->create()
        ;
    }

    /** Signs in, opens the agenda and keeps its `appointment` token. */
    private function openAgenda(): void
    {
        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"email":"decide.vet@example.com","password":"mot-de-passe"}');
        self::assertResponseIsSuccessful();

        $this->browser->request('GET', self::HOST . '/scheduling/agenda');
        self::assertResponseIsSuccessful();
        self::assertSame(1, preg_match('/"token":"([^"]+)"/', (string) $this->browser->getResponse()->getContent(), $match));
        $this->token = $match[1];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function json(string $action, string $id): array
    {
        $this->browser->request(
            'POST',
            self::HOST . '/scheduling/appointments/' . $id . '/' . $action,
            ['_token' => $this->token],
            server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
        );

        $answer = json_decode((string) $this->browser->getResponse()->getContent(), true);

        return \is_array($answer) ? $answer : [];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        \assert($service instanceof $id);

        return $service;
    }

    private function statusOf(string $id): ?string
    {
        $bus = self::getContainer()->get(QueryBusInterface::class);
        \assert($bus instanceof QueryBusInterface);

        $items = $bus->ask(new GetAgendaForClinicDateRange(self::CLINIC, $this->now->modify('-1 day'), $this->now->modify('+3 days')));
        \assert(\is_array($items));

        foreach ($items as $item) {
            if ($item instanceof AppointmentItem && $item->id === $id) {
                return $item->status;
            }
        }

        return null;
    }
}
