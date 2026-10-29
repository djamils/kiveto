<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Pipeline;

use App\Context\Client\Application\Command\ArchiveClient\ArchiveClient;
use App\Context\Client\Application\Command\UnarchiveClient\UnarchiveClient;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClinicId as ClientClinicId;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Context\ClientPortal\Application\Command\AcceptPortalInvitation\AcceptOutcome;
use App\Context\ClientPortal\Application\Command\AcceptPortalInvitation\AcceptResult;
use App\Context\ClientPortal\Application\Command\AcceptPortalInvitationWithCode\AcceptPortalInvitationWithCode;
use App\Context\ClientPortal\Application\Command\AcceptPortalInvitationWithGrant\AcceptPortalInvitationWithGrant;
use App\Context\ClientPortal\Application\Command\SendPortalInvitation\SendPortalInvitation;
use App\Context\ClientPortal\Application\Query\GetClientPortalStatus\ClientPortalStatus;
use App\Context\ClientPortal\Application\Query\GetClientPortalStatus\GetClientPortalStatus;
use App\Context\ClientPortal\Application\Query\GetOwnerProfile\GetOwnerProfile;
use App\Context\ClientPortal\Application\Query\GetOwnerProfile\OwnerProfileDetails;
use App\Context\ClientPortal\Application\Query\GetPortalInvitationByToken\GetPortalInvitationByToken;
use App\Context\ClientPortal\Application\Query\GetPortalInvitationByToken\PortalInvitationDetails;
use App\Context\ClientPortal\Application\Query\HasConfirmedClientLink\HasConfirmedClientLink;
use App\Context\ClientPortal\Application\Query\ListOwnerClinics\ListOwnerClinics;
use App\Context\ClientPortal\Application\Query\ListOwnerClinics\OwnerClinic;
use App\Context\ClientPortal\Application\Query\ListPendingInvitationsForPhone\ListPendingInvitationsForPhone;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Embeddable\LetterheadEmbeddable;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Shared\Domain\ValueObject\PostalAddress;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\OtpChallengeIssued;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\RequestPortalOtp;
use App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp\LoginOtpOutcome;
use App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp\VerifyPortalLoginOtp;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use App\Tests\Support\MessengerDrain;
use App\Tests\Support\RecordingCommandBus;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * The invitation through the real buses and the real IdentityAccess side:
 * the clinic sends it, the owner proves the phone and accepts, the archive
 * of the client withdraws the access.
 */
final class PortalInvitationPipelineTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC = '01960000-0000-7000-8000-0000000c3c01';
    private const string CLIENT = '01960000-0000-7000-8000-0000000c3d01';
    private const string ACTOR  = '01960000-0000-7000-8000-0000000c3e01';
    private const string PHONE  = '+33612340931';

    protected function setUp(): void
    {
        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique des Lilas')->withLetterhead(new LetterheadEmbeddable(streetLine1: '4 place Bellecour', postalCode: '69002', city: 'Lyon', countryCode: 'FR'))->create();

        $clients = self::getContainer()->get(ClientRepositoryInterface::class);
        \assert($clients instanceof ClientRepositoryInterface);
        $client = Client::create(
            ClientId::fromString(self::CLIENT),
            ClientClinicId::fromString(self::CLINIC),
            new ClientIdentity('Sophie', 'Martin', Civility::MADAM),
            [
                ContactMethod::phone(PhoneNumber::fromString(self::PHONE), ContactLabel::MOBILE, true),
                ContactMethod::email(EmailAddress::fromString('sophie.portal@example.com'), ContactLabel::HOME, true),
            ],
            new \DateTimeImmutable('2026-01-12 10:00:00'),
        );
        $client->updatePostalAddress(PostalAddress::create('12 rue des Lilas', 'Lyon', 'FR', null, '69003'), new \DateTimeImmutable('2026-01-12 10:00:00'));
        $clients->save($client);
    }

    public function testTheOwnerAcceptsTheLinkWithTheCodeSentToTheMobile(): void
    {
        [$invitationId, $token] = $this->invite();

        // The message is journaled for the client by Notification.
        self::assertSame(1, $this->number('SELECT COUNT(*) FROM notification__outbound_messages WHERE client_id = ? AND subject_id = ?', [Uuid::fromString(self::CLIENT)->toBinary(), $invitationId]));

        $details = $this->ask(new GetPortalInvitationByToken($token));
        self::assertInstanceOf(PortalInvitationDetails::class, $details);
        self::assertSame([$invitationId, 'Clinique des Lilas', 'Lyon', 'sophie.portal@example.com', self::PHONE], [$details->invitationId, $details->clinicName, $details->city, $details->email, $details->phone]);

        [$challengeId, $code] = $this->code('invitation', $invitationId);
        $outcome              = $this->accept(new AcceptPortalInvitationWithCode($token, $challengeId, $code, $this->terms()));
        self::assertSame(AcceptResult::ACCEPTED, $outcome->result);
        $userId = $outcome->userId;
        self::assertNotNull($userId);

        $user = $this->connection()->fetchAssociative('SELECT type, phone, email, phone_verified_at, email_verified_at FROM identity_access__users WHERE id = ?', [Uuid::fromString($userId)->toBinary()]);
        self::assertIsArray($user);
        self::assertSame(['PORTAL', self::PHONE, 'sophie.portal@example.com', null], [$user['type'], $user['phone'], $user['email'], $user['email_verified_at']]);
        self::assertNotNull($user['phone_verified_at']);

        $profile = $this->ask(new GetOwnerProfile($userId));
        self::assertInstanceOf(OwnerProfileDetails::class, $profile);
        self::assertSame(['Sophie', 'Martin', '12 rue des Lilas', '69003', 'Lyon', 'FR', $this->terms()], [$profile->firstName, $profile->lastName, $profile->address?->line1, $profile->address?->postalCode, $profile->address?->city, $profile->address?->country, $profile->termsVersion]);

        $clinics = $this->ask(new ListOwnerClinics($userId));
        self::assertIsArray($clinics);
        self::assertSame([[self::CLINIC, 'Clinique des Lilas', 'Lyon']], array_map(static fn (mixed $c): array => $c instanceof OwnerClinic ? [$c->clinicId, $c->clinicName, $c->city] : [], $clinics));
        self::assertTrue($this->ask(new HasConfirmedClientLink($userId, self::CLINIC)));
        self::assertSame(ClientPortalStatus::ACTIVE, $this->portalStatus()->state);
        self::assertSame([], $this->ask(new ListPendingInvitationsForPhone(self::PHONE)));

        // AC 72: the same request again (its answer was lost) is accepted again, nothing duplicated.
        $retry = $this->accept(new AcceptPortalInvitationWithCode($token, $challengeId, $code, $this->terms()));
        self::assertSame([AcceptResult::ACCEPTED, $userId], [$retry->result, $retry->userId]);
        self::assertSame([1, 1], [
            $this->number('SELECT COUNT(*) FROM identity_access__users WHERE phone = ?', [self::PHONE]),
            $this->number('SELECT COUNT(*) FROM client_portal__client_links WHERE client_id = ?', [Uuid::fromString(self::CLIENT)->toBinary()]),
        ]);
    }

    /** AC 52: wrong codes are committed by the real bus, the invitation stays pending. */
    public function testWrongCodesCostTheirAttemptsAndAcceptNothing(): void
    {
        [$invitationId, $token] = $this->invite();
        [$challengeId, $code]   = $this->code('invitation', $invitationId);

        $wrong = '000000' === $code ? '111111' : '000000';
        for ($i = 1; $i <= 5; ++$i) {
            self::assertSame(5 === $i ? AcceptResult::OTP_ATTEMPTS_EXCEEDED : AcceptResult::OTP_INVALID, $this->accept(new AcceptPortalInvitationWithCode($token, $challengeId, $wrong, $this->terms()))->result);
        }

        self::assertSame(AcceptResult::OTP_ATTEMPTS_EXCEEDED, $this->accept(new AcceptPortalInvitationWithCode($token, $challengeId, $code, $this->terms()))->result);
        self::assertSame(5, $this->number('SELECT attempts FROM identity_access__otp_challenges WHERE id = ?', [Uuid::fromString($challengeId)->toBinary()]));
        self::assertSame(ClientPortalStatus::INVITED, $this->portalStatus()->state);
    }

    /** D17 / AC 30: a failure after the account was claimed leaves nothing behind. */
    public function testAFailureAfterTheClaimRollsEverythingBack(): void
    {
        [$invitationId, $token] = $this->invite();
        [$challengeId, $code]   = $this->code('invitation', $invitationId);

        self::getContainer()->set(OwnerProfileRepositoryInterface::class, new class implements OwnerProfileRepositoryInterface {
            public function save(OwnerProfile $profile): void
            {
                throw new \RuntimeException('Profile store down.');
            }

            public function findByUserId(string $userId): ?OwnerProfile
            {
                return null;
            }
        });

        try {
            $this->accept(new AcceptPortalInvitationWithCode($token, $challengeId, $code, $this->terms()));
            self::fail('The acceptance should have failed.');
        } catch (\RuntimeException $e) {
            self::assertSame('Profile store down.', $e->getMessage());
        }

        self::assertSame([0, 0, 0], [
            $this->number('SELECT COUNT(*) FROM identity_access__users WHERE phone = ?', [self::PHONE]),
            $this->number('SELECT COUNT(*) FROM client_portal__client_links WHERE client_id = ?', [Uuid::fromString(self::CLIENT)->toBinary()]),
            $this->number('SELECT COUNT(*) FROM client_portal__owner_profiles'),
        ]);
        self::assertSame(ClientPortalStatus::INVITED, $this->portalStatus()->state);
        self::assertSame(0, $this->number('SELECT COUNT(*) FROM identity_access__otp_challenges WHERE id = ? AND consumed_at IS NOT NULL', [Uuid::fromString($challengeId)->toBinary()]));
    }

    /** D29 / AC 56, 70: the phone sign-in earns a grant that accepts the invitation, then dies. */
    public function testThePhoneSignInGrantAcceptsTheInvitation(): void
    {
        [$invitationId]       = $this->invite();
        [$challengeId, $code] = $this->code('login', null);

        $login = $this->bus()->dispatch(new VerifyPortalLoginOtp($challengeId, $code));
        self::assertInstanceOf(LoginOtpOutcome::class, $login);
        $grant = $login->onboardingToken;
        self::assertNotNull($grant);

        $outcome = $this->accept(new AcceptPortalInvitationWithGrant($invitationId, $grant, $this->terms()));
        self::assertSame(AcceptResult::ACCEPTED, $outcome->result);
        self::assertSame(1, $this->number('SELECT COUNT(*) FROM identity_access__otp_challenges WHERE phone = ? AND onboarding_revoked_at IS NOT NULL', [self::PHONE]), 'last invitation accepted: the grant is revoked');

        // The grant is gone: a retry answers like any dead grant.
        self::assertSame(AcceptResult::ONBOARDING_INVALID, $this->accept(new AcceptPortalInvitationWithGrant($invitationId, $grant, $this->terms()))->result);
    }

    /** D8 / AC 31: archiving withdraws the access; unarchiving restores nothing until a new acceptance. */
    public function testArchivingTheClientWithdrawsTheAccess(): void
    {
        [$invitationId, $token] = $this->invite();
        [$challengeId, $code]   = $this->code('invitation', $invitationId);
        $userId                 = $this->accept(new AcceptPortalInvitationWithCode($token, $challengeId, $code, $this->terms()))->userId;
        self::assertNotNull($userId);

        $this->bus()->dispatch(new ArchiveClient(self::CLINIC, self::CLIENT));
        MessengerDrain::drain(self::getContainer(), ['async']);

        self::assertFalse($this->ask(new HasConfirmedClientLink($userId, self::CLINIC)));
        self::assertSame([], $this->ask(new ListOwnerClinics($userId)));
        self::assertSame(ClientPortalStatus::REVOKED, $this->portalStatus()->state);

        $this->bus()->dispatch(new UnarchiveClient(self::CLINIC, self::CLIENT));
        self::assertSame(ClientPortalStatus::REVOKED, $this->portalStatus()->state, 'unarchiving restores nothing');

        [$again, $newToken] = $this->invite();
        self::assertNotSame($invitationId, $again, '« Réinviter » creates a new invitation');
        self::assertSame(ClientPortalStatus::INVITED, $this->portalStatus()->state);

        [$challengeId, $code] = $this->code('invitation', $again);
        $outcome              = $this->accept(new AcceptPortalInvitationWithCode($newToken, $challengeId, $code, $this->terms()));
        self::assertSame([AcceptResult::ACCEPTED, $userId], [$outcome->result, $outcome->userId], 'the same account is linked again');
        self::assertTrue($this->ask(new HasConfirmedClientLink($userId, self::CLINIC)));
    }

    /**
     * @return array{0: string, 1: string} [invitation id, token]
     */
    private function invite(): array
    {
        $this->recorder()->reset();
        $id = $this->bus()->dispatch(new SendPortalInvitation(self::CLINIC, self::CLIENT, 'sms', self::ACTOR));
        \assert(\is_string($id));

        $requests = array_values(array_filter($this->recorder()->recorded(RequestNotification::class), static fn (RequestNotification $r): bool => 'PORTAL_INVITATION' === $r->purpose));
        self::assertCount(1, $requests);
        self::assertSame(['SMS', self::PHONE, self::CLINIC, self::CLIENT, 'portal_invitation', $id], [$requests[0]->channel, $requests[0]->recipientAddress, $requests[0]->clinicId, $requests[0]->clientId, $requests[0]->subjectType, $requests[0]->subjectId]);
        $link = $requests[0]->parameters['link'];
        \assert(\is_string($link));
        self::assertStringStartsWith('http://portal.kiveto.local/invitation#t=', $link);

        return [$id, rawurldecode(substr($link, (int) strpos($link, '#t=') + 3))];
    }

    /**
     * @return array{0: string, 1: string} [challenge id, code]
     */
    private function code(string $purpose, ?string $invitationId): array
    {
        $this->recorder()->reset();
        $issued = $this->bus()->dispatch(new RequestPortalOtp(self::PHONE, $purpose, $invitationId));
        self::assertInstanceOf(OtpChallengeIssued::class, $issued);

        $requests = array_values(array_filter($this->recorder()->recorded(RequestNotification::class), static fn (RequestNotification $r): bool => 'PORTAL_OTP' === $r->purpose));
        $code     = $requests[0]->parameters['code'] ?? null;
        \assert(\is_string($code));

        return [$issued->challengeId, $code];
    }

    private function accept(object $command): AcceptOutcome
    {
        $outcome = $this->bus()->dispatch($command);
        self::assertInstanceOf(AcceptOutcome::class, $outcome);

        return $outcome;
    }

    private function portalStatus(): ClientPortalStatus
    {
        $status = $this->ask(new GetClientPortalStatus(self::CLINIC, self::CLIENT));
        self::assertInstanceOf(ClientPortalStatus::class, $status);

        return $status;
    }

    private function terms(): string
    {
        $version = $_SERVER['PORTAL_TERMS_VERSION'] ?? $_ENV['PORTAL_TERMS_VERSION'] ?? null;
        \assert(\is_string($version));

        return $version;
    }

    private function ask(object $query): mixed
    {
        $bus = self::getContainer()->get(QueryBusInterface::class);
        \assert($bus instanceof QueryBusInterface);

        return $bus->ask($query);
    }

    private function bus(): CommandBusInterface
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus;
    }

    private function recorder(): RecordingCommandBus
    {
        $bus = self::getContainer()->get(RecordingCommandBus::class);
        \assert($bus instanceof RecordingCommandBus);

        return $bus;
    }

    /**
     * @param list<string> $parameters
     */
    private function number(string $sql, array $parameters = []): int
    {
        $value = $this->connection()->fetchOne($sql, $parameters);

        return is_numeric($value) ? (int) $value : -1;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);

        return $connection;
    }
}
