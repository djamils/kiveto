<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Pipeline;

use App\Context\Client\Application\Command\ArchiveClient\ArchiveClient;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClinicId as ClientClinicId;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Context\ClientPortal\Application\Command\DeletePortalAccount\DeletePortalAccountHandler;
use App\Context\ClientPortal\Application\Command\ResolvePortalRequest\ResolvePortalRequest;
use App\Context\ClientPortal\Application\Service\PortalRequestAccountErasure;
use App\Context\ClientPortal\Domain\Exception\PortalRequestAlreadyResolvedException;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\PetChangeRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\PrescriptionRenewalRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaireRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Context\Prescription\Domain\Event\PrescriptionCancelledIntegrationEvent;
use App\Context\Scheduling\Domain\Event\PendingAppointmentCancelledIntegrationEvent;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\IntegrationEventBusInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use App\Tests\Support\MessengerDrain;
use App\Tests\Support\RecordingCommandBus;
use App\Tests\Unit\Context\ClientPortal\Support\Requests;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * AC 230, 238: the requests through the real buses — closed by the
 * consumers of the other contexts' events, never notified; answered once,
 * with one message to the owner on the client's transactional channel.
 */
final class PortalRequestClosurePipelineTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC        = '01960000-0000-7000-8000-0000000c7c01';
    private const string CLIENT        = '01960000-0000-7000-8000-0000000c7d01';
    private const string USER          = '01960000-0000-7000-8000-0000000c7e01';
    private const string PET           = '01960000-0000-7000-8000-0000000c7f01';
    private const string PRESCRIPTION  = '01960000-0000-7000-8000-0000000c7b01';
    private const string APPOINTMENT   = '01960000-0000-7000-8000-0000000c7a01';
    private const string CHANGE        = '01960000-0000-7000-8000-0000000c7001';
    private const string RENEWAL       = '01960000-0000-7000-8000-0000000c7002';
    private const string QUESTIONNAIRE = '01960000-0000-7000-8000-0000000c7003';

    protected function setUp(): void
    {
        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique des Lilas')->create();

        $clients = self::service(ClientRepositoryInterface::class);
        $clients->save(Client::create(
            ClientId::fromString(self::CLIENT),
            ClientClinicId::fromString(self::CLINIC),
            new ClientIdentity('Sophie', 'Martin', Civility::MADAM),
            [
                ContactMethod::phone(PhoneNumber::fromString('+33612340932'), ContactLabel::MOBILE, true),
                ContactMethod::email(EmailAddress::fromString('sophie.requests@example.com'), ContactLabel::HOME, true),
            ],
            new \DateTimeImmutable('2026-01-12 10:00:00'),
        ));
        self::service(ClientLinkRepositoryInterface::class)->save(ClientLink::confirm(ClientLinkId::fromString('01960000-0000-7000-8000-0000000c7901'), self::USER, self::CLINIC, self::CLIENT, Requests::utc('2026-10-01 08:00:00')));

        self::service(PetChangeRequestRepositoryInterface::class)->save(Requests::change(self::CHANGE, petId: self::PET, userId: self::USER, clientId: self::CLIENT, clinicId: self::CLINIC));
        self::service(PrescriptionRenewalRequestRepositoryInterface::class)->add(Requests::renewal(self::RENEWAL, self::PRESCRIPTION, petId: self::PET, userId: self::USER, clientId: self::CLIENT, clinicId: self::CLINIC));
        self::service(PreVisitQuestionnaireRepositoryInterface::class)->save(Requests::questionnaire(self::QUESTIONNAIRE, self::APPOINTMENT, userId: self::USER, clientId: self::CLIENT, clinicId: self::CLINIC));
    }

    public function testArchivingTheClientClosesItsOpenRequestsWithoutMessage(): void
    {
        self::service(CommandBusInterface::class)->dispatch(new ArchiveClient(self::CLINIC, self::CLIENT));
        MessengerDrain::drain(self::getContainer(), ['async']);

        self::assertSame(
            [[RequestStatus::CLOSED, ClosureReason::CLIENT_ARCHIVED], [RequestStatus::CLOSED, ClosureReason::CLIENT_ARCHIVED], [RequestStatus::CLOSED, ClosureReason::CLIENT_ARCHIVED]],
            $this->states(),
        );
        self::assertSame([], $this->resolutionMessages());
    }

    public function testACancelledPrescriptionAndACancelledPendingBookingCloseWhatHangsOnThem(): void
    {
        self::service(IntegrationEventBusInterface::class)->publish([], new PrescriptionCancelledIntegrationEvent(self::PRESCRIPTION, self::CLINIC), new PendingAppointmentCancelledIntegrationEvent(self::APPOINTMENT, self::CLINIC, self::CLIENT, self::PET, '2026-11-05 09:00:00'));
        MessengerDrain::drain(self::getContainer(), ['async']);

        self::assertSame(
            [[RequestStatus::SUBMITTED, null], [RequestStatus::CLOSED, ClosureReason::PRESCRIPTION_CANCELLED], [RequestStatus::CLOSED, ClosureReason::APPOINTMENT_CANCELLED]],
            $this->states(),
        );
    }

    public function testAnAnsweredRequestSendsExactlyOneMessageOnTheTransactionalChannel(): void
    {
        $bus = self::service(CommandBusInterface::class);
        self::service(RecordingCommandBus::class)->reset();

        self::assertSame('done', $bus->dispatch(new ResolvePortalRequest(self::CLINIC, self::CHANGE, 'done', '01960000-0000-7000-8000-0000000c7801', 'Dr Claire Lambert', 'Poids noté.')));

        try {
            $bus->dispatch(new ResolvePortalRequest(self::CLINIC, self::CHANGE, 'rejected', '01960000-0000-7000-8000-0000000c7802', 'Hugo ASV'));
            self::fail('The second answer should be refused.');
        } catch (PortalRequestAlreadyResolvedException $e) {
            self::assertSame('Dr Claire Lambert', $e->resolvedByName);
        }

        [$message] = $this->resolutionMessages();
        self::assertSame(
            ['EMAIL', 'sophie.requests@example.com', self::CLINIC, self::CLIENT, 'portal_request', self::CHANGE, 'portal-request-resolved:' . self::CHANGE, 'Votre demande de modification pour votre animal a été traitée.', 'Poids noté.'],
            [$message->channel, $message->recipientAddress, $message->clinicId, $message->clientId, $message->subjectType, $message->subjectId, $message->idempotencyKey, $message->parameters['sentence'] ?? null, $message->parameters['staffMessage'] ?? null],
        );
        self::assertNotNull(self::service(PetChangeRequestRepositoryInterface::class)->findById(self::CLINIC, self::CHANGE)?->notificationMessageId());
    }

    public function testTheAccountDeletionRunsTheRequestsErasureStep(): void
    {
        $steps = new \ReflectionProperty(DeletePortalAccountHandler::class, 'steps')->getValue(self::service(DeletePortalAccountHandler::class));
        \assert(is_iterable($steps));

        self::assertContains(PortalRequestAccountErasure::class, array_map(static fn (mixed $step): string => \is_object($step) ? $step::class : '', [...$steps]));
    }

    /**
     * @return list<array{RequestStatus|null, ClosureReason|null}> change, renewal, questionnaire
     */
    private function states(): array
    {
        $change        = self::service(PetChangeRequestRepositoryInterface::class)->findById(self::CLINIC, self::CHANGE);
        $renewal       = self::service(PrescriptionRenewalRequestRepositoryInterface::class)->findById(self::CLINIC, self::RENEWAL);
        $questionnaire = self::service(PreVisitQuestionnaireRepositoryInterface::class)->findByAppointment(self::CLINIC, self::APPOINTMENT);

        return [
            [$change?->status(), $change?->closedReason()],
            [$renewal?->status(), $renewal?->closedReason()],
            [$questionnaire?->status(), $questionnaire?->closedReason()],
        ];
    }

    /**
     * @return list<RequestNotification>
     */
    private function resolutionMessages(): array
    {
        return array_values(array_filter(
            self::service(RecordingCommandBus::class)->recorded(RequestNotification::class),
            static fn (RequestNotification $n): bool => 'PORTAL_REQUEST_RESOLVED' === $n->purpose,
        ));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private static function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        \assert($service instanceof $id);

        return $service;
    }
}
