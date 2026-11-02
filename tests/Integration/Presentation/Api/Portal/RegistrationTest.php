<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\AccessMapInterface;

/**
 * Slice 7, S7a / S7a': organic arrival — `registration_required` hands a
 * registration token, `POST /auth/register` creates the account (public
 * route), and the unknown-number SMS budget in three layers.
 */
final class RegistrationTest extends PortalApiTestCase
{
    private const string TERMS = '2026-10-29';

    /** AC 72: token → account and session; a replay within 15 minutes → same account, new pair; then 401. */
    public function testAnUnknownPhoneRegistersAndMayReplayItsToken(): void
    {
        $this->clientIp = '198.51.100.31';
        $answer         = $this->verifiedUnknown('+33615000001');
        self::assertSame('registration_required', $answer['status']);
        $token = self::string($answer['registrationToken']);
        self::assertNotNull($answer['registrationTokenExpiresAt']);
        self::assertNull($answer['onboardingToken']);

        $created = self::json($this->register($token, email: ' Sophie.Reg@Example.com '), 201);
        self::assertIsArray($created['account']);
        $account = $created['account'];
        self::assertSame(['Sophie', 'Martin', 'sophie.reg@example.com', false, '+33 6 •• •• •• 01', null], [
            $account['firstName'], $account['lastName'], $account['email'], $account['emailVerified'], $account['maskedPhone'], $account['address'],
        ]);
        $me = self::json($this->get('/me', ['HTTP_AUTHORIZATION' => 'Bearer ' . self::string($created['accessToken'])]), 200);
        self::assertIsArray($me['account']);
        self::assertSame($account['id'], $me['account']['id']);

        $replay = self::json($this->register($token, firstName: 'Autre'), 201);
        self::assertIsArray($replay['account']);
        self::assertSame([$account['id'], 'Sophie'], [$replay['account']['id'], $replay['account']['firstName']], 'the same account, its profile untouched');
        self::assertNotSame($created['refreshToken'], $replay['refreshToken']);
        self::assertSame(1, $this->number('SELECT COUNT(*) FROM identity_access__users WHERE phone = ?', ['+33615000001']));

        $this->connection()->executeStatement('UPDATE identity_access__otp_challenges SET onboarding_expires_at = ? WHERE phone = ?', ['2020-01-01 00:00:00', '+33615000001']);
        $expired = $this->register($token);
        self::assertSame('onboarding_invalid', self::problemType($expired, 401));
        self::assertSame('Jeton expiré ou invalide.', self::json($expired, 401)['title']);
    }

    /** AC 73: an unknown token is refused, and the route is public but anchored. */
    public function testAnUnknownTokenIsRefused(): void
    {
        $this->clientIp = '198.51.100.32';

        self::assertSame('onboarding_invalid', self::problemType($this->register('not-a-token'), 401));

        $accessMap = $this->browser->getContainer()->get('security.access_map');
        \assert($accessMap instanceof AccessMapInterface);
        self::assertSame([AuthenticatedVoter::PUBLIC_ACCESS], $accessMap->getPatterns(Request::create(self::BASE . '/auth/register', 'POST'))[0]);
        self::assertSame([AuthenticatedVoter::IS_AUTHENTICATED_FULLY], $accessMap->getPatterns(Request::create(self::BASE . '/auth/registered', 'POST'))[0], 'anchored: only /auth/register itself is public');
    }

    /** AC 262: an account created after the token was issued wins; the token stays unused. */
    public function testAnExistingAccountAnswersAccountExists(): void
    {
        $this->clientIp = '198.51.100.33';
        $token          = self::string($this->verifiedUnknown('+33615000003')['registrationToken']);
        PortalUserFactory::new()->withPhone('+33615000003')->create();

        self::assertSame('account_exists', self::problemType($this->register($token), 409));
        self::assertSame(1, $this->number('SELECT COUNT(*) FROM identity_access__users WHERE phone = ?', ['+33615000003']));
        self::assertSame(0, $this->number('SELECT COUNT(*) FROM identity_access__otp_challenges WHERE phone = ? AND onboarding_consumed_at IS NOT NULL', ['+33615000003']));
        self::assertSame(0, $this->number('SELECT COUNT(*) FROM client_portal__owner_profiles p JOIN identity_access__users u ON u.id = p.user_id WHERE u.phone = ?', ['+33615000003']));
    }

    /** AC 263: outdated terms create nothing and keep the token usable. */
    public function testOutdatedTermsCreateNothing(): void
    {
        $this->clientIp = '198.51.100.34';
        $token          = self::string($this->verifiedUnknown('+33615000004')['registrationToken']);

        self::assertSame('terms_outdated', self::problemType($this->register($token, terms: '2020-01-01'), 422));
        self::assertSame(0, $this->number('SELECT COUNT(*) FROM identity_access__users WHERE phone = ?', ['+33615000004']));

        self::json($this->register($token), 201);
    }

    public function testInvalidInputIsAValidationProblemAndAUsedEmailIsDropped(): void
    {
        $this->clientIp = '198.51.100.35';
        $token          = self::string($this->verifiedUnknown('+33615000005')['registrationToken']);

        self::assertSame('validation_failed', self::problemType($this->register($token, firstName: '   '), 422));
        self::assertSame('validation_failed', self::problemType($this->register($token, firstName: str_repeat('a', 61)), 422));
        self::assertSame('validation_failed', self::problemType($this->register($token, email: 'not-an-email'), 422));

        PortalUserFactory::new()->with(['email' => 'taken@example.com'])->create();
        $created = self::json($this->register($token, email: 'taken@example.com'), 201);
        self::assertIsArray($created['account']);
        self::assertNull($created['account']['email']);
    }

    public function testTenRegistrationsAnHourPerIp(): void
    {
        $this->clientIp = '198.51.100.36';
        for ($i = 0; $i < 10; ++$i) {
            self::problemType($this->register('token-' . $i), 401);
        }

        $refused = $this->register('token-10');
        self::assertSame('rate_limited', self::problemType($refused, 429));
        self::assertGreaterThan(0, (int) $refused->headers->get('Retry-After'));
    }

    /** AC 261: 20 unknown numbers an hour per /24; a rotating X-Device-Id does not bypass it; another network still gets its SMS. */
    public function testTheNetworkSubLimitSuppressesTheTwentyFirstSms(): void
    {
        for ($i = 1; $i <= 21; ++$i) {
            $this->clientIp = '192.0.2.' . $i;
            self::json($this->post('/auth/otp/request', ['phone' => \sprintf('+336160%05d', $i)], ['HTTP_X_DEVICE_ID' => 'device-' . $i]), 202);
        }

        self::assertSame(20, $this->otpSmsCount());
        self::assertSame(1, $this->number('SELECT sms_suppressed FROM identity_access__otp_challenges WHERE phone = ?', ['+33616000021']), 'same 202, no SMS');

        $this->clientIp = '192.0.3.1';
        $this->requestCode('+33616000022');
        self::assertSame(21, $this->otpSmsCount(), 'another network still receives its code');
    }

    public function testTheIpv6NetworkIsItsSlash64(): void
    {
        for ($i = 1; $i <= 21; ++$i) {
            $this->clientIp = '2001:db8:aa:bb::' . dechex($i);
            $this->requestCode(\sprintf('+336170%05d', $i));
        }

        self::assertSame(20, $this->otpSmsCount());
        $this->clientIp = '2001:db8:aa:bc::1';
        $this->requestCode('+33617000022');
        self::assertSame(21, $this->otpSmsCount());
    }

    /** S7-05: a resend to an unknown number spends the network budget too; exhausted, the same 202 and no SMS. */
    public function testAResendToAnUnknownNumberSpendsTheBudget(): void
    {
        $this->clientIp = '192.0.4.10';
        $this->limiter('limiter.portal_otp_unknown_network')->create('192.0.4.0/24')->consume(19);

        $challengeId = $this->requestCode('+33616100001');
        self::assertSame(1, $this->otpSmsCount(), 'the last SMS of the network');

        $this->connection()->executeStatement('UPDATE identity_access__otp_challenges SET resend_available_at = ? WHERE phone = ?', ['2020-01-01 00:00:00', '+33616100001']);
        self::assertSame($challengeId, self::json($this->post('/auth/otp/' . $challengeId . '/resend', ['channel' => 'sms'], ['HTTP_X_DEVICE_ID' => 'device-resend']), 202)['challengeId']);
        self::assertSame(1, $this->otpSmsCount(), 'same 202, no SMS');
    }

    /** The device hint: 5 an hour, whatever the network. */
    public function testTheDeviceHintLimitsOneDevice(): void
    {
        for ($i = 1; $i <= 6; ++$i) {
            $this->clientIp = '198.18.' . $i . '.1';
            $this->post('/auth/otp/request', ['phone' => \sprintf('+336180%05d', $i)], ['HTTP_X_DEVICE_ID' => 'same-device']);
        }

        self::assertSame(5, $this->otpSmsCount());
    }

    /** AC 261: the global cap suppresses every unknown number and its alert is spent once. */
    public function testTheGlobalCapIsTheLastLayer(): void
    {
        $this->clientIp = '198.51.100.40';
        $global         = $this->limiter('limiter.portal_otp_unknown_global')->create('global');
        while ($global->consume()->isAccepted()) {
            // drain the hourly budget
        }

        $this->requestCode('+33619000001');
        $this->requestCode('+33619000002');

        self::assertSame(0, $this->otpSmsCount());
        self::assertSame(0, $this->limiter('limiter.portal_otp_unknown_alert')->create('global')->consume(0)->getRemainingTokens(), 'the critical log was written once');
        self::assertSame(18, $this->limiter('limiter.portal_otp_unknown_network')->create('198.51.100.0/24')->consume(0)->getRemainingTokens(), 'the network was spent first');
    }

    /**
     * Requests and verifies a sign-in code for a phone without account.
     *
     * @return array<string, mixed>
     */
    private function verifiedUnknown(string $phone): array
    {
        $challengeId = $this->requestCode($phone);

        return self::json($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $this->lastOtpCode()]), 200);
    }

    private function register(string $token, string $firstName = 'Sophie', ?string $email = null, string $terms = self::TERMS): Response
    {
        return $this->post('/auth/register', ['registrationToken' => $token, 'firstName' => $firstName, 'lastName' => 'Martin', 'email' => $email, 'termsVersion' => $terms]);
    }

    private function limiter(string $id): RateLimiterFactoryInterface
    {
        $limiter = $this->browser->getContainer()->get('test.' . $id);
        \assert($limiter instanceof RateLimiterFactoryInterface);

        return $limiter;
    }
}
