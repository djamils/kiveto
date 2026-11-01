<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClinicId as ClientClinicId;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Context\ClientPortal\Application\Command\DeletePortalAccount\DeletePortalAccount;
use App\Context\ClientPortal\Application\Command\SendPortalInvitation\SendPortalInvitation;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Embeddable\LetterheadEmbeddable;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Pdf\PdfRendererInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use App\Tests\Support\Document\RecordingPdfRenderer;
use App\Tests\Support\MessengerDrain;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Slice 5: the account deletion (S5g; AC 64, 65, 211, 212, 213). The 202
 * disables the account; the erasure runs from the `async` queue, drained
 * in the test.
 */
final class AccountDeletionTest extends PortalApiTestCase
{
    private const string CLINIC_A = '01960000-0000-7000-8000-0000000a3701';
    private const string CLINIC_B = '01960000-0000-7000-8000-0000000a3702';
    private const string ACTOR    = '01960000-0000-7000-8000-0000000a3703';
    private const string PHONE    = '+33614370001';
    private const string EMAIL    = 'claire.deletion@example.com';

    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.137';
        parent::setUp();
        $this->browser->getContainer()->set(PdfRendererInterface::class, new RecordingPdfRenderer());
        ClinicEntityFactory::new()->withId(self::CLINIC_A)->withName('Clinique des Lilas')->withLetterhead(new LetterheadEmbeddable(streetLine1: '4 place Bellecour', postalCode: '69002', city: 'Lyon', countryCode: 'FR'))->create();
        ClinicEntityFactory::new()->withId(self::CLINIC_B)->withName('Clinique du Port')->withLetterhead(new LetterheadEmbeddable(streetLine1: '1 quai Est', postalCode: '13002', city: 'Marseille', countryCode: 'FR'))->create();
    }

    /** AC 64: a wrong code, a sign-in code or another user's code is refused and nothing changes. */
    public function testOnlyTheOwnersDeletionCodeIsAccepted(): void
    {
        PortalUserFactory::new()->withPhone(self::PHONE)->create();
        PortalUserFactory::new()->withPhone('+33614370002')->create();
        $alice = $this->bearerOf(self::PHONE);
        $bob   = $this->bearerOf('+33614370002');

        $aliceCode = self::json($this->post('/me/deletion-request/otp', [], $alice), 202);
        $code      = $this->lastOtpCode();
        $wrong     = '000000' === $code ? '111111' : '000000';
        self::assertSame('otp_invalid', self::problemType($this->post('/me/deletion-request', ['challengeId' => $aliceCode['challengeId'], 'code' => $wrong], $alice), 422), 'wrong code');
        self::assertSame('otp_invalid', self::problemType($this->post('/me/deletion-request', ['challengeId' => $aliceCode['challengeId'], 'code' => $code], $bob), 422), 'the code of another user');

        $login = $this->requestCode(self::PHONE);
        self::assertSame('otp_invalid', self::problemType($this->post('/me/deletion-request', ['challengeId' => $login, 'code' => $this->lastOtpCode()], $alice), 422), 'a sign-in code');

        self::assertSame(200, $this->get('/me', $alice)->getStatusCode(), 'nothing changed');
        self::assertSame('ACTIVE', $this->connection()->fetchOne('SELECT status FROM identity_access__users WHERE phone = ?', [self::PHONE]));
        self::assertSame(2, $this->number('SELECT attempts FROM identity_access__otp_challenges WHERE id = UUID_TO_BIN(?)', [$aliceCode['challengeId']]), 'the wrong attempts are kept');

        $answer = self::json($this->post('/me/deletion-request', ['challengeId' => '01960000-0000-7000-8000-000000000000', 'code' => '12'], $alice), 422);
        self::assertSame('validation_failed', $answer['type']);
        self::assertSame('unauthorized', self::problemType($this->post('/me/deletion-request/otp', []), 401));
    }

    /** AC 213: the SMS names the deletion, not a sign-in. */
    public function testTheDeletionCodeSmsNamesTheDeletion(): void
    {
        PortalUserFactory::new()->withPhone(self::PHONE)->create();
        $bearer = $this->bearerOf(self::PHONE);

        $challenge = self::json($this->post('/me/deletion-request/otp', [], $bearer), 202);
        self::assertSame(['challengeId', 'expiresAt', 'resendAvailableAt'], array_keys($challenge));
        $sms = $this->lastNotification('PORTAL_OTP');
        self::assertSame(['SMS', self::PHONE, 'account_deletion'], [$sms->channel, $sms->recipientAddress, $sms->parameters['purpose'] ?? null]);
        self::assertSame(['account_deletion', 'user'], array_values((array) $this->connection()->fetchAssociative('SELECT purpose, subject_type FROM identity_access__otp_challenges WHERE id = UUID_TO_BIN(?)', [$challenge['challengeId']])));

        $content = self::string($this->connection()->fetchOne('SELECT content FROM notification__outbound_messages WHERE idempotency_key = ?', ['otp:' . self::string($challenge['challengeId']) . ':1']));
        self::assertStringContainsString('Code de confirmation de suppression de votre compte Kiveto', $content);
        self::assertStringNotContainsString('connexion', $content);
    }

    /** AC 65, 211, 212: tokens refused at once; the erasure, retried, leaves the clinic's records untouched. */
    public function testTheAccountIsDisabledAtOnceThenErased(): void
    {
        $clientId = $this->client(self::CLINIC_A);
        $accepted = $this->acceptInvitation(self::CLINIC_A, $clientId);
        $userId   = self::string(self::array($accepted['account'])['id']);
        $bearer   = ['HTTP_AUTHORIZATION' => 'Bearer ' . self::string($accepted['accessToken'])];
        $this->invite(self::CLINIC_B, $this->client(self::CLINIC_B));
        $pendingId = self::string($this->connection()->fetchOne('SELECT BIN_TO_UUID(id) FROM client_portal__portal_invitations WHERE clinic_id = UUID_TO_BIN(?)', [self::CLINIC_B]));

        // A sign-in with the pending invitation gives the challenge an onboarding grant.
        $signIn = self::json($this->post('/auth/otp/verify', ['challengeId' => $this->requestCode(self::PHONE), 'code' => $this->lastOtpCode()]), 200);
        self::assertIsString($signIn['onboardingToken'] ?? null);

        // An export, ready, and its signed download URL.
        $exportId = self::string(self::json($this->post('/me/data-export', [], $bearer), 202)['id'] ?? null);
        MessengerDrain::drain($this->browser->getContainer(), ['async_external']);
        $signedUrl = (string) $this->get('/me/data-exports/' . $exportId . '/file', $bearer)->headers->get('Location');
        self::assertSame(200, $this->download($signedUrl)->getStatusCode());
        self::assertTrue($this->stored('client_portal/exports/' . $exportId . '.zip'));

        $this->connection()->executeStatement(
            'INSERT INTO shared__idempotency_keys (principal_id, idempotency_key, request_fingerprint, response_status, response_body, created_at, expires_at) VALUES (?, ?, ?, 201, NULL, NOW(), NOW() + INTERVAL 1 DAY)',
            [Uuid::fromString($userId)->toBinary(), 'booking-1', str_repeat('a', 64)],
        );
        self::assertSame(2, $this->limiterRows(), 'the phone\'s hourly and daily quotas are counted');

        $clientRow  = $this->clientRow($clientId);
        $refresh    = self::string($accepted['refreshToken']);
        $deletionId = self::json($this->post('/me/deletion-request/otp', [], $bearer), 202)['challengeId'];

        // The codes are handed over (the journal keeps their recipient until the erasure).
        MessengerDrain::drain($this->browser->getContainer(), ['async_external_notification']);
        self::assertGreaterThan(0, $this->number("SELECT COUNT(*) FROM notification__outbound_messages WHERE recipient_address = ? AND purpose = 'PORTAL_OTP' AND status <> 'QUEUED'", [self::PHONE]));

        self::assertSame(202, $this->post('/me/deletion-request', ['challengeId' => $deletionId, 'code' => $this->lastOtpCode()], $bearer)->getStatusCode());

        self::assertSame('unauthorized', self::problemType($this->get('/me', $bearer), 401), 'the access token is refused at once');
        self::assertSame(401, $this->post('/auth/refresh', ['refreshToken' => $refresh])->getStatusCode(), 'and the refresh');
        self::assertSame(0, $this->number('SELECT COUNT(*) FROM identity_access__refresh_tokens WHERE user_id = UUID_TO_BIN(?) AND revoked_at IS NULL', [$userId]));
        self::assertSame(['DISABLED', self::PHONE], array_values((array) $this->connection()->fetchAssociative('SELECT status, phone FROM identity_access__users WHERE id = UUID_TO_BIN(?)', [$userId])), 'erased later');

        MessengerDrain::drain($this->browser->getContainer(), ['async']);

        self::assertSame(['revoked'], $this->connection()->fetchFirstColumn('SELECT status FROM client_portal__client_links WHERE user_id = UUID_TO_BIN(?)', [$userId]));
        self::assertSame('revoked', $this->connection()->fetchOne('SELECT status FROM client_portal__portal_invitations WHERE id = UUID_TO_BIN(?)', [$pendingId]));
        self::assertSame(0, $this->number('SELECT COUNT(*) FROM identity_access__otp_challenges WHERE phone = ? OR subject_ref = ?', [self::PHONE, $userId]), 'challenges and grants gone');
        self::assertSame(
            ['Compte', 'supprimé', null, null],
            array_values((array) $this->connection()->fetchAssociative('SELECT first_name, last_name, address_line1, address_city FROM client_portal__owner_profiles WHERE user_id = UUID_TO_BIN(?)', [$userId])),
        );
        $user = (array) $this->connection()->fetchAssociative('SELECT status, phone, email, email_verified_at, anonymised_at FROM identity_access__users WHERE id = UUID_TO_BIN(?)', [$userId]);
        self::assertSame(['DISABLED', null, null, null], [$user['status'], $user['phone'], $user['email'], $user['email_verified_at']]);
        self::assertNotNull($user['anonymised_at']);

        // AC 211: what is keyed by the account or its phone is gone; Kiveto's messages are masked.
        self::assertSame(0, $this->number('SELECT COUNT(*) FROM shared__idempotency_keys WHERE principal_id = UUID_TO_BIN(?)', [$userId]));
        self::assertSame(0, $this->limiterRows());
        self::assertSame(0, $this->number('SELECT COUNT(*) FROM identity_access__portal_phone_locks WHERE phone = ?', [self::PHONE]));
        self::assertSame(0, $this->number("SELECT COUNT(*) FROM notification__outbound_messages WHERE recipient_address = ? AND purpose = 'PORTAL_OTP'", [self::PHONE]));
        self::assertGreaterThan(0, $this->number("SELECT COUNT(*) FROM notification__outbound_messages WHERE recipient_address = ? AND purpose = 'PORTAL_INVITATION'", [self::PHONE]), 'the clinics\' invitations stay in their journal');

        // AC 212: the export and its archive are gone.
        self::assertSame(0, $this->number('SELECT COUNT(*) FROM client_portal__data_exports WHERE user_id = UUID_TO_BIN(?)', [$userId]));
        self::assertFalse($this->stored('client_portal/exports/' . $exportId . '.zip'));
        self::assertSame('not_found', self::problemType($this->download($signedUrl), 404));

        // The clinic's client record (and its consents) is untouched.
        self::assertSame($clientRow, $this->clientRow($clientId));

        // A retry of the job finds nothing left to do.
        $queue = $this->browser->getContainer()->get('messenger.bus.command');
        \assert($queue instanceof MessageBusInterface);
        $queue->dispatch(new DeletePortalAccount($userId));
        self::assertSame(1, MessengerDrain::drain($this->browser->getContainer(), ['async']));
        self::assertSame(['Compte', 'supprimé'], array_values((array) $this->connection()->fetchAssociative('SELECT first_name, last_name FROM client_portal__owner_profiles WHERE user_id = UUID_TO_BIN(?)', [$userId])));

        // AC 65: a new invitation to the phone creates a new account.
        $again = $this->acceptInvitation(self::CLINIC_A, $clientId);
        self::assertNotSame($userId, self::array($again['account'])['id']);
        self::assertSame(1, $this->number("SELECT COUNT(*) FROM identity_access__users WHERE phone = ? AND type = 'PORTAL'", [self::PHONE]));
        self::assertSame(200, $this->get('/me', ['HTTP_AUTHORIZATION' => 'Bearer ' . self::string($again['accessToken'])])->getStatusCode());
    }

    /**
     * Invites the client by SMS, then accepts with the code of the link.
     *
     * @return array<string, mixed> the acceptance answer
     */
    private function acceptInvitation(string $clinicId, string $clientId): array
    {
        [, $token]   = $this->invite($clinicId, $clientId);
        $challengeId = self::string(self::json($this->post('/invitations/otp', ['token' => $token]), 202)['challengeId']);

        return self::json($this->post('/invitations/accept', ['token' => $token, 'challengeId' => $challengeId, 'code' => $this->lastOtpCode(), 'termsVersion' => '2026-10-29']), 200);
    }

    /**
     * @return array{string, string} the invitation id and the clear token of its link
     */
    private function invite(string $clinicId, string $clientId): array
    {
        $invitationId = $this->commandBus()->dispatch(new SendPortalInvitation($clinicId, $clientId, 'sms', self::ACTOR));
        self::assertIsString($invitationId);
        $link = self::string($this->lastNotification('PORTAL_INVITATION')->parameters['link'] ?? null);

        return [$invitationId, substr($link, (int) strpos($link, '#t=') + 3)];
    }

    private function client(string $clinicId): string
    {
        $clientId = Uuid::v7()->toRfc4122();
        $clients  = $this->browser->getContainer()->get(ClientRepositoryInterface::class);
        \assert($clients instanceof ClientRepositoryInterface);
        $clients->save(Client::create(
            ClientId::fromString($clientId),
            ClientClinicId::fromString($clinicId),
            new ClientIdentity('Claire', 'Deletion', Civility::MADAM),
            [ContactMethod::phone(PhoneNumber::fromString(self::PHONE), ContactLabel::MOBILE, true), ContactMethod::email(EmailAddress::fromString(self::EMAIL), ContactLabel::HOME, true)],
            new \DateTimeImmutable('2026-01-12 10:00:00'),
        ));

        return $clientId;
    }

    /**
     * @return array<mixed>
     */
    private function clientRow(string $clientId): array
    {
        return (array) $this->connection()->fetchAssociative('SELECT * FROM client__clients WHERE id = UUID_TO_BIN(?)', [$clientId]);
    }

    /** Rows of the phone's two quotas in the limiter table (own connection). */
    private function limiterRows(): int
    {
        $limiter = $this->browser->getContainer()->get('doctrine.dbal.rate_limiter_connection');
        \assert($limiter instanceof Connection);
        $ids = array_map(static fn (string $limiter): string => 'portal:' . sha1($limiter . '-' . self::PHONE), ['portal_otp_phone', 'portal_otp_phone_daily']);

        $count = $limiter->fetchOne('SELECT COUNT(*) FROM shared__rate_limiter_cache WHERE item_id IN (?, ?)', $ids);
        self::assertIsNumeric($count);

        return (int) $count;
    }

    /**
     * @return array<string, string>
     */
    private function bearerOf(string $phone): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer ' . self::string($this->signIn($phone)['accessToken'])];
    }

    private function lastNotification(string $purpose): RequestNotification
    {
        $sent = array_values(array_filter($this->bus()->recorded(RequestNotification::class), static fn (RequestNotification $n): bool => $purpose === $n->purpose));
        self::assertNotSame([], $sent, $purpose . ' was not requested');

        return $sent[\count($sent) - 1];
    }

    private function download(string $url): Response
    {
        $this->browser->request('GET', $url, server: ['REMOTE_ADDR' => $this->clientIp]);

        return $this->browser->getResponse();
    }

    private function stored(string $key): bool
    {
        $storage = $this->browser->getContainer()->get(FileStorageInterface::class);
        \assert($storage instanceof FileStorageInterface);

        try {
            $storage->read($key);

            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    private function commandBus(): CommandBusInterface
    {
        $bus = $this->browser->getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus;
    }

    /**
     * @return array<mixed>
     */
    private static function array(mixed $value): array
    {
        self::assertIsArray($value);

        return $value;
    }
}
