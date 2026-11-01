<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Adapter;

use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Animal\Application\Query\GetAnimalById\IdentificationDto;
use App\Context\Animal\Application\Query\GetAnimalById\LifeCycleDto;
use App\Context\Animal\Application\Query\GetAnimalById\OwnershipDto;
use App\Context\Animal\Application\Query\GetAnimalById\TransferDto;
use App\Context\ClientPortal\Application\Port\PortalRequestResolvedMessage;
use App\Context\ClientPortal\Domain\Exception\PortalRequestRateLimitedException;
use App\Context\ClientPortal\Infrastructure\Adapter\Animal\QueryBusPetDirectory;
use App\Context\ClientPortal\Infrastructure\Adapter\Clinic\QueryBusPrescriberDirectory;
use App\Context\ClientPortal\Infrastructure\Adapter\Consultation\QueryBusConsultationStarts;
use App\Context\ClientPortal\Infrastructure\Adapter\Notification\CommandBusNotificationRequester;
use App\Context\ClientPortal\Infrastructure\Adapter\Notification\CommandBusPortalRequestBell;
use App\Context\ClientPortal\Infrastructure\RateLimit\RateLimiterPortalRequestQuota;
use App\Context\Clinic\Application\Query\Staff\ListClinicMembersForNotification\ListClinicMembersForNotification;
use App\Context\Clinic\Application\Query\Staff\ListPermissionsForUserInClinic\ListPermissionsForUserInClinic;
use App\Context\Consultation\Application\Query\ListAppointmentsWithConsultation\ListAppointmentsWithConsultation;
use App\System\Notification\Application\Command\PostInAppNotification\PostInAppNotification;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use App\Tests\Support\AnsweringQueryBus;
use App\Tests\Unit\Context\ClientPortal\Support\AnsweringCommandBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/** The slice-6 adapters: pet files, consultations started, staff, bell, owner message, quota. */
final class RequestAdaptersTest extends TestCase
{
    public function testThePetFilesAreAnimalsArchivedOnesIncluded(): void
    {
        $bus = new AnsweringQueryBus([GetAnimalById::class => static fn (GetAnimalById $q): ?AnimalView => match ($q->animalId) {
            'a1'    => self::animal('a1', 'active', 'alive', [new OwnershipDto('client-1', 'primary', 'active', 's', null)]),
            'a2'    => self::animal('a2', 'archived', 'deceased', []),
            'a3'    => throw new \DomainException('Animal not found.'),
            default => null,
        }]);

        $states = new QueryBusPetDirectory($bus)->statesOf('clinic-1', ['a1', 'a2', 'a1', 'a3', 'a4']);

        self::assertSame(['a1', 'a2'], array_keys($states));
        self::assertSame(['a1', 'Moka', 'client-1', 'neutered', '250269812345678', 'alive', false, true, null], [
            $states['a1']->id, $states['a1']->name, $states['a1']->primaryOwnerClientId, $states['a1']->reproductiveStatus, $states['a1']->microchipNumber,
            $states['a1']->lifeStatus, $states['a1']->archived, $states['a1']->isAlive(), $states['a1']->closureState(),
        ]);
        self::assertSame([null, true, false, 'pet_archived'], [$states['a2']->primaryOwnerClientId, $states['a2']->archived, $states['a2']->isAlive(), $states['a2']->closureState()]);
        self::assertCount(4, $bus->asked, 'one read per distinct animal');
    }

    public function testADeceasedActivePetClosesItsRequestsAsDeceased(): void
    {
        $bus = new AnsweringQueryBus([GetAnimalById::class => self::animal('a1', 'active', 'deceased', [])]);

        self::assertSame('pet_deceased', new QueryBusPetDirectory($bus)->statesOf('clinic-1', ['a1'])['a1']->closureState());
    }

    public function testTheConsultationsStartedAreConsultations(): void
    {
        $bus    = new AnsweringQueryBus([ListAppointmentsWithConsultation::class => ['rdv-1', 42]]);
        $starts = new QueryBusConsultationStarts($bus);

        self::assertSame(['rdv-1'], $starts->startedFrom('clinic-1', ['rdv-1', 'rdv-2']));
        $query = $bus->asked[0];
        self::assertInstanceOf(ListAppointmentsWithConsultation::class, $query);
        self::assertSame(['clinic-1', ['rdv-1', 'rdv-2']], [$query->clinicId, $query->appointmentIds]);

        self::assertSame([], $starts->startedFrom('clinic-1', []));
        self::assertCount(1, $bus->asked);
        self::assertSame([], new QueryBusConsultationStarts(new AnsweringQueryBus())->startedFrom('clinic-1', ['rdv-1']));
    }

    public function testTheVeterinariansAndTheAnimalManagersAreClinicMembers(): void
    {
        $bus = new AnsweringQueryBus([
            ListClinicMembersForNotification::class => static fn (ListClinicMembersForNotification $q): array => null === $q->role ? ['vet-1', 'asv-1', 'rec-1'] : ['vet-1'],
            ListPermissionsForUserInClinic::class   => static fn (ListPermissionsForUserInClinic $q): ?array => match ($q->userId) {
                'vet-1' => ['manage_animals', 'create_prescription'],
                'asv-1' => ['manage_animals'],
                default => null,
            },
        ]);
        $staff = new QueryBusPrescriberDirectory($bus);

        self::assertSame(['vet-1'], $staff->veterinarians('clinic-1'));
        $query = $bus->asked[0];
        self::assertInstanceOf(ListClinicMembersForNotification::class, $query);
        self::assertSame(['clinic-1', 'VETERINARY'], [$query->clinicId, $query->role]);

        self::assertSame(['vet-1', 'asv-1'], $staff->animalManagers('clinic-1'));
        self::assertSame([], new QueryBusPrescriberDirectory(new AnsweringQueryBus())->veterinarians('clinic-1'));
    }

    public function testTheBellIsOneEntryPerRequestOpeningIt(): void
    {
        $bus  = new AnsweringCommandBus(static fn (object $command): null => null);
        $bell = new CommandBusPortalRequestBell($bus);

        $bell->announce('clinic-1', ['vet-1', 'asv-1', 'vet-1'], 'req-1', 'Demande de modification — Moka', 'Sophie Martin · Poids');
        $bell->announce('clinic-1', [], 'req-2', 'Demande', 'Personne');

        self::assertCount(1, $bus->commands);
        $command = $bus->commands[0];
        self::assertInstanceOf(PostInAppNotification::class, $command);
        self::assertSame(
            ['clinic-1', ['vet-1', 'asv-1'], 'PORTAL_REQUEST', 'Demande de modification — Moka', 'Sophie Martin · Poids', 'clinic_portal_requests_show', ['id' => 'req-1'], 'portal-request:req-1'],
            [$command->clinicId, $command->userIds, $command->kind, $command->title, $command->detail, $command->route, $command->routeParams, $command->idempotencyKey],
        );
    }

    public function testTheAnswerIsTheClinicsMessageOnTheTransactionalChannel(): void
    {
        $bus       = new AnsweringCommandBus(static fn (object $command): string => 'message-1');
        $requester = new CommandBusNotificationRequester($bus);

        self::assertSame('message-1', $requester->requestPortalRequestResolved(self::message('email', 's@example.com', null)));
        $requester->requestPortalRequestResolved(self::message('sms', '+33612345678', 's@example.com'));
        $requester->requestPortalRequestResolved(self::message('sms', '+33612345678', null));

        [$email, $sms, $smsOnly] = $bus->commands;
        self::assertInstanceOf(RequestNotification::class, $email);
        self::assertSame(
            ['PORTAL_REQUEST_RESOLVED', 'EMAIL', 's@example.com', 'Sophie Martin', 'clinic-1', 'client-1', 'portal_request', 'req-1', 'portal-request-resolved:req-1', null],
            [$email->purpose, $email->channel, $email->recipientAddress, $email->recipientName, $email->clinicId, $email->clientId, $email->subjectType, $email->subjectId, $email->idempotencyKey, $email->fallback],
        );
        $parameters = ['addressee' => 'Sophie Martin', 'petName' => 'Moka', 'sentence' => 'Votre demande de modification pour Moka a été traitée.', 'staffMessage' => 'Merci.'];
        self::assertSame($parameters, $email->parameters);

        self::assertInstanceOf(RequestNotification::class, $sms);
        self::assertSame(['SMS', ['sentence' => 'Votre demande de modification pour Moka a été traitée.', 'staffMessage' => 'Merci.']], [$sms->channel, $sms->parameters]);
        self::assertSame(['s@example.com', 'Sophie Martin', $parameters], [$sms->fallback?->address, $sms->fallback?->name, $sms->fallback?->parameters]);
        self::assertInstanceOf(RequestNotification::class, $smsOnly);
        self::assertNull($smsOnly->fallback);
    }

    public function testTenRequestsPerAccountAndClinicPer24Hours(): void
    {
        $quota = new RateLimiterPortalRequestQuota(new RateLimiterFactory(['id' => 'portal_requests', 'policy' => 'sliding_window', 'limit' => 10, 'interval' => '24 hours'], new InMemoryStorage()));

        for ($i = 0; $i < 10; ++$i) {
            $quota->consume('user-1', 'clinic-1');
        }
        $quota->consume('user-1', 'clinic-2');

        try {
            $quota->consume('user-1', 'clinic-1');
            self::fail('The eleventh request should be refused.');
        } catch (PortalRequestRateLimitedException $e) {
            self::assertGreaterThan(0, $e->retryAfterSeconds);
            self::assertLessThanOrEqual(86_400, $e->retryAfterSeconds);
        }
    }

    private static function message(string $channel, string $address, ?string $fallback): PortalRequestResolvedMessage
    {
        return new PortalRequestResolvedMessage('req-1', 'clinic-1', 'client-1', $channel, $address, 'Sophie Martin', 'Moka', 'Votre demande de modification pour Moka a été traitée.', 'Merci.', $fallback);
    }

    /**
     * @param list<OwnershipDto> $ownerships
     */
    private static function animal(string $id, string $status, string $life, array $ownerships): AnimalView
    {
        return new AnimalView(
            $id,
            'clinic-1',
            'Moka',
            'cat',
            'female',
            'neutered',
            true,
            null,
            null,
            null,
            null,
            new IdentificationDto('250269812345678', null, null, 'icad', null, null),
            new LifeCycleDto($life, null, null),
            new TransferDto('none', null, null),
            null,
            $status,
            $ownerships,
            'c',
            'u',
        );
    }
}
