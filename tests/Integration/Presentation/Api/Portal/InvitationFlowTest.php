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
use App\Context\ClientPortal\Application\Command\SendPortalInvitation\SendPortalInvitation;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Embeddable\LetterheadEmbeddable;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The owner's way in: the invitation link (lookup, code, acceptance), the
 * phone sign-in with an onboarding grant, /me, and the retry rules.
 */
final class InvitationFlowTest extends PortalApiTestCase
{
    private const string CLINIC_A = '01960000-0000-7000-8000-0000000a1c01';
    private const string CLINIC_B = '01960000-0000-7000-8000-0000000a1c02';
    private const string ACTOR    = '01960000-0000-7000-8000-0000000a1e01';

    /** The client file created last: « Renvoyer l'invitation » targets it again. */
    private string $lastClientId = '';

    protected function setUp(): void
    {
        parent::setUp();
        ClinicEntityFactory::new()->withId(self::CLINIC_A)->withName('Clinique des Lilas')->withLetterhead(new LetterheadEmbeddable(streetLine1: '4 place Bellecour', streetLine2: 'Bât. B', postalCode: '69002', city: 'Lyon', countryCode: 'FR', phone: '+33478380000'))->create();
        ClinicEntityFactory::new()->withId(self::CLINIC_B)->withName('Clinique du Port')->withLetterhead(new LetterheadEmbeddable(streetLine1: '1 quai Est', postalCode: '13002', city: 'Marseille', countryCode: 'FR'))->create();
    }

    /** AC 25, 26, 32, 72: the link flow, then /me, then a retry of the lost answer. */
    public function testTheOwnerAcceptsTheLinkAndSeesTheClinic(): void
    {
        [$invitationId, $token] = $this->invite(self::CLINIC_A, '+33614000001', 'sophie.flow@example.com');

        $lookup = self::json($this->post('/invitations/lookup', ['token' => $token]), 200);
        self::assertSame($invitationId, $lookup['invitationId']);
        self::assertSame(['id' => self::CLINIC_A, 'name' => 'Clinique des Lilas', 'city' => 'Lyon'], $lookup['clinic']);
        self::assertSame(['sophie.flow@example.com', '+33 6 •• •• •• 01'], [$lookup['email'], $lookup['maskedPhone']]);

        $challengeId = self::string(self::json($this->post('/invitations/otp', ['token' => $token]), 202)['challengeId']);
        $code        = $this->lastOtpCode();
        $body        = ['token' => $token, 'challengeId' => $challengeId, 'code' => $code, 'termsVersion' => '2026-10-29'];
        $accepted    = self::json($this->post('/invitations/accept', $body), 200);

        $account = $accepted['account'];
        self::assertIsArray($account);
        self::assertSame(['Sophie', 'Flow', 'sophie.flow@example.com', '+33 6 •• •• •• 01'], [$account['firstName'], $account['lastName'], $account['email'], $account['maskedPhone']]);

        $me      = self::json($this->get('/me', ['HTTP_AUTHORIZATION' => 'Bearer ' . self::string($accepted['accessToken'])]), 200);
        $clinics = $me['clinics'];
        self::assertIsArray($clinics);
        self::assertCount(1, $clinics);
        self::assertIsArray($clinics[0]);
        self::assertSame([self::CLINIC_A, 'Clinique des Lilas', 'confirmed', [], 0], [$clinics[0]['clinicId'], $clinics[0]['name'], $clinics[0]['linkStatus'], $clinics[0]['tags'], $clinics[0]['unreadDocuments']]);
        self::assertSame(['+33478380000', ['line' => '4 place Bellecour, Bât. B', 'postalCode' => '69002', 'city' => 'Lyon']], [$clinics[0]['phone'], $clinics[0]['address']], "the clinic's letterhead contact");

        $retry = self::json($this->post('/invitations/accept', $body), 200);
        self::assertSame($account['id'], self::account($retry)['id']);
        self::assertNotSame($accepted['refreshToken'], $retry['refreshToken'], 'a fresh pair');
        self::assertSame(1, $this->number('SELECT COUNT(*) FROM identity_access__users WHERE phone = ?', ['+33614000001']));
    }

    /** AC 24, AC 47, AC 27, AC 28. */
    public function testTheLinkRoutesRefuseWhatTheyMust(): void
    {
        self::assertSame('invitation_invalid', self::problemType($this->post('/invitations/lookup', ['token' => 'unknown']), 404));

        [$invitationId, $token] = $this->invite(self::CLINIC_A, '+33614000002', null);
        $this->connection()->executeStatement('UPDATE client_portal__portal_invitations SET expires_at = ? WHERE id = ?', ['2020-01-01 00:00:00', Uuid::fromString($invitationId)->toBinary()]);
        self::assertSame('invitation_expired', self::problemType($this->post('/invitations/lookup', ['token' => $token]), 410));

        [, $token] = $this->invite(self::CLINIC_B, '+33614000003', null);
        $login     = $this->requestCode('+33614000003');
        self::assertSame('otp_invalid', self::problemType($this->post('/invitations/accept', ['token' => $token, 'challengeId' => $login, 'code' => $this->lastOtpCode(), 'termsVersion' => '2026-10-29']), 422), 'a sign-in code is no invitation code');

        $challengeId = self::string(self::json($this->post('/invitations/otp', ['token' => $token]), 202)['challengeId']);
        self::assertSame('terms_outdated', self::problemType($this->post('/invitations/accept', ['token' => $token, 'challengeId' => $challengeId, 'code' => $this->lastOtpCode(), 'termsVersion' => '2020-01-01']), 422));
        self::assertSame(0, $this->number('SELECT COUNT(*) FROM identity_access__users WHERE phone = ?', ['+33614000003']), 'nothing written');
    }

    /** AC 56, 69, 70: phone sign-in, two invitations, one grant; the grant opens nothing else. */
    public function testAPhoneWithInvitationsSignsInAndAcceptsThemWithTheGrant(): void
    {
        [$first]  = $this->invite(self::CLINIC_A, '+33614000004', null);
        [$second] = $this->invite(self::CLINIC_B, '+33614000004', null);

        $challengeId = $this->requestCode('+33614000004');
        $answer      = self::json($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $this->lastOtpCode()]), 200);
        self::assertSame('invitation_pending', $answer['status']);
        $grant = self::string($answer['onboardingToken']);
        self::assertIsArray($answer['invitations']);
        self::assertCount(2, $answer['invitations']);

        self::assertSame('unauthorized', self::problemType($this->get('/me', ['HTTP_AUTHORIZATION' => 'Bearer ' . $grant]), 401), 'a grant is no access token');
        self::assertSame('refresh_revoked', self::problemType($this->post('/auth/refresh', ['refreshToken' => $grant]), 401));

        $accepted = self::json($this->post('/invitations/' . $first . '/accept', ['onboardingToken' => $grant, 'termsVersion' => '2026-10-29']), 200);
        self::json($this->post('/invitations/' . $second . '/accept', ['onboardingToken' => $grant, 'termsVersion' => '2026-10-29']), 200);
        self::assertSame(1, $this->number('SELECT COUNT(*) FROM identity_access__otp_challenges WHERE phone = ? AND onboarding_revoked_at IS NOT NULL', ['+33614000004']), 'revoked once none is pending');

        $me = self::json($this->get('/me', ['HTTP_AUTHORIZATION' => 'Bearer ' . self::string($accepted['accessToken'])]), 200);
        self::assertIsArray($me['clinics']);
        self::assertCount(2, $me['clinics'], 'AC 29: one account, two clinics');
        $port = array_values(array_filter($me['clinics'], static fn (mixed $clinic): bool => \is_array($clinic) && self::CLINIC_B === $clinic['clinicId']));
        self::assertCount(1, $port);
        self::assertSame([null, ['line' => '1 quai Est', 'postalCode' => '13002', 'city' => 'Marseille']], [$port[0]['phone'], $port[0]['address']], 'a letterhead without a phone');

        self::assertSame('onboarding_invalid', self::problemType($this->post('/invitations/' . $second . '/accept', ['onboardingToken' => $grant, 'termsVersion' => '2026-10-29']), 401), 'the lost-answer retry: sign in again');
    }

    /** AC 73: neither route accepts the other's grant. */
    public function testRegistrationAndInvitationGrantsStayOnTheirRoute(): void
    {
        $challengeId    = $this->requestCode('+33614000020');
        $registration   = self::string(self::json($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $this->lastOtpCode()]), 200)['registrationToken']);
        [$invitationId] = $this->invite(self::CLINIC_A, '+33614000020', null);

        self::assertSame('onboarding_invalid', self::problemType($this->post('/invitations/' . $invitationId . '/accept', ['onboardingToken' => $registration, 'termsVersion' => '2026-10-29']), 401));

        $this->connection()->executeStatement('UPDATE identity_access__otp_challenges SET resend_available_at = ? WHERE phone = ?', ['2020-01-01 00:00:00', '+33614000020']);
        $challengeId = $this->requestCode('+33614000020');
        $answer      = self::json($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $this->lastOtpCode()]), 200);
        self::assertSame(['invitation_pending', null], [$answer['status'], $answer['registrationToken']]);

        $body = ['registrationToken' => self::string($answer['onboardingToken']), 'firstName' => 'Sophie', 'lastName' => 'Flow', 'termsVersion' => '2026-10-29'];
        self::assertSame('onboarding_invalid', self::problemType($this->post('/auth/register', $body), 401));
        self::assertSame(0, $this->number('SELECT COUNT(*) FROM identity_access__users WHERE phone = ?', ['+33614000020']));
    }

    /** AC 68: a grant of phone A never accepts an invitation of phone B. */
    public function testAGrantIsBoundToItsPhone(): void
    {
        [$ofA] = $this->invite(self::CLINIC_A, '+33614000005', null);
        [$ofB] = $this->invite(self::CLINIC_B, '+33614000006', null);

        $challengeId = $this->requestCode('+33614000005');
        $grant       = self::string(self::json($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $this->lastOtpCode()]), 200)['onboardingToken']);

        self::assertSame('invitation_invalid', self::problemType($this->post('/invitations/' . $ofB . '/accept', ['onboardingToken' => $grant, 'termsVersion' => '2026-10-29']), 404));
        self::assertSame(0, $this->number('SELECT COUNT(*) FROM identity_access__users WHERE phone IN (?, ?)', ['+33614000005', '+33614000006']));
        self::json($this->post('/invitations/' . $ofA . '/accept', ['onboardingToken' => $grant, 'termsVersion' => '2026-10-29']), 200);
    }

    /** AC 79: « Renvoyer l'invitation » keeps the id the app holds. */
    public function testAResentInvitationKeepsItsIdForTheGrant(): void
    {
        [$invitationId, $firstToken] = $this->invite(self::CLINIC_A, '+33614000007', null);
        $challengeId                 = $this->requestCode('+33614000007');
        $grant                       = self::string(self::json($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $this->lastOtpCode()]), 200)['onboardingToken']);

        [$sameId] = $this->invite(self::CLINIC_A, '+33614000007', null, $this->lastClientId);
        self::assertSame($invitationId, $sameId);
        self::assertSame('invitation_invalid', self::problemType($this->post('/invitations/lookup', ['token' => $firstToken]), 404), 'AC 23: the old link is dead');

        self::json($this->post('/invitations/' . $invitationId . '/accept', ['onboardingToken' => $grant, 'termsVersion' => '2026-10-29']), 200);
    }

    /** AC 77: a disabled owner accepts nothing and the invitation stays pending. */
    public function testADisabledOwnerAcceptsNothing(): void
    {
        PortalUserFactory::new()->withPhone('+33614000008')->disabled()->create();
        [$invitationId, $token] = $this->invite(self::CLINIC_A, '+33614000008', null);

        $challengeId = self::string(self::json($this->post('/invitations/otp', ['token' => $token]), 202)['challengeId']);
        self::assertSame('account_disabled', self::problemType($this->post('/invitations/accept', ['token' => $token, 'challengeId' => $challengeId, 'code' => $this->lastOtpCode(), 'termsVersion' => '2026-10-29']), 403));
        self::assertSame('pending', $this->connection()->fetchOne('SELECT status FROM client_portal__portal_invitations WHERE id = ?', [Uuid::fromString($invitationId)->toBinary()]));
    }

    /** AC 63: invitation codes never spend the unknown-number budget. */
    public function testInvitationCodesIgnoreTheUnknownNumberBudget(): void
    {
        $budget = $this->browser->getContainer()->get('test.limiter.portal_otp_unknown_global');
        \assert($budget instanceof RateLimiterFactoryInterface);
        $limiter = $budget->create('global');
        while ($limiter->consume()->isAccepted()) {
            // drain the hourly budget
        }

        [, $token] = $this->invite(self::CLINIC_A, '+33614000009', null);
        $before    = $this->otpSmsCount();
        self::json($this->post('/invitations/otp', ['token' => $token]), 202);

        self::assertSame($before + 1, $this->otpSmsCount());
    }

    /**
     * A client of the clinic with this mobile, invited by SMS.
     *
     * @return array{string, string} the invitation id and the clear token of its link
     */
    private function invite(string $clinicId, string $phone, ?string $email, ?string $clientId = null): array
    {
        $clientId ??= $this->client($clinicId, $phone, $email);

        $commandBus = $this->browser->getContainer()->get(CommandBusInterface::class);
        \assert($commandBus instanceof CommandBusInterface);
        $invitationId = $commandBus->dispatch(new SendPortalInvitation($clinicId, $clientId, 'sms', self::ACTOR));
        self::assertIsString($invitationId);

        $sent = array_values(array_filter($this->bus()->recorded(RequestNotification::class), static fn (RequestNotification $n): bool => 'PORTAL_INVITATION' === $n->purpose));
        $link = $sent[\count($sent) - 1]->parameters['link'] ?? null;
        self::assertIsString($link);
        self::assertStringContainsString('/invitation#t=', $link, 'AC 60: the token only travels in the fragment');

        return [$invitationId, substr($link, (int) strpos($link, '#t=') + 3)];
    }

    private function client(string $clinicId, string $phone, ?string $email): string
    {
        $clientId = Uuid::v7()->toRfc4122();
        $contacts = [ContactMethod::phone(PhoneNumber::fromString($phone), ContactLabel::MOBILE, true)];
        if (null !== $email) {
            $contacts[] = ContactMethod::email(EmailAddress::fromString($email), ContactLabel::HOME, true);
        }

        $clients = $this->browser->getContainer()->get(ClientRepositoryInterface::class);
        \assert($clients instanceof ClientRepositoryInterface);
        $clients->save(Client::create(ClientId::fromString($clientId), ClientClinicId::fromString($clinicId), new ClientIdentity('Sophie', 'Flow', Civility::MADAM), $contacts, new \DateTimeImmutable('2026-01-12 10:00:00')));
        $this->lastClientId = $clientId;

        return $clientId;
    }

    /**
     * @param array<string, mixed> $answer
     *
     * @return array<mixed>
     */
    private static function account(array $answer): array
    {
        self::assertIsArray($answer['account']);

        return $answer['account'];
    }
}
