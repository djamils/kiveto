<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

/**
 * Sign-in by one-time code: request, verify, and every error the app must
 * handle. The code is read from the SMS the API asked Notification to send.
 */
final class OtpSignInTest extends PortalApiTestCase
{
    public function testTheTermsArePublic(): void
    {
        self::assertSame(['version' => '2026-10-29', 'url' => 'http://portal.kiveto.local/cgu'], self::json($this->get('/legal/terms/current'), 200));
    }

    /** AC 7, AC 13: the same 202 for a known and an unknown phone. */
    public function testAKnownOwnerSignsInAndAnUnknownPhoneIsAskedToRegister(): void
    {
        $owner = PortalUserFactory::new()->withPhone('+33611000001')->create();

        $known = self::json($this->post('/auth/otp/request', ['phone' => '+33611000001']), 202);
        self::assertSame(['challengeId', 'expiresAt', 'resendAvailableAt'], array_keys($known));
        $expiresAt = new \DateTimeImmutable(self::string($known['expiresAt']));
        $resendAt  = new \DateTimeImmutable(self::string($known['resendAvailableAt']));
        self::assertSame(570, $expiresAt->getTimestamp() - $resendAt->getTimestamp(), '10 min of validity, a resend after 30 s');

        $answer = self::json($this->post('/auth/otp/verify', ['challengeId' => $known['challengeId'], 'code' => $this->lastOtpCode()]), 200);
        self::assertSame('authenticated', $answer['status']);
        self::assertSame([], $answer['pendingInvitations']);
        self::assertNull($answer['onboardingToken']);

        $encoder = $this->browser->getContainer()->get(JWTEncoderInterface::class);
        \assert($encoder instanceof JWTEncoderInterface);
        $claims = $encoder->decode(self::string($answer['accessToken']));
        self::assertSame([$owner->getId()->toRfc4122(), 'portal'], [$claims['sub'] ?? null, $claims['typ'] ?? null]);
        self::assertIsInt($claims['exp'] ?? null);
        self::assertEqualsWithDelta(time() + 900, $claims['exp'], 5);
        self::assertSame(43, \strlen(self::string($answer['refreshToken'])));

        $unknown = self::json($this->post('/auth/otp/request', ['phone' => '+33611000002']), 202);
        self::assertSame(['challengeId', 'expiresAt', 'resendAvailableAt'], array_keys($unknown), 'nothing tells the phones apart');
        $answer = self::json($this->post('/auth/otp/verify', ['challengeId' => $unknown['challengeId'], 'code' => $this->lastOtpCode()]), 200);
        self::assertSame(['registration_required', null, null], [$answer['status'], $answer['registrationToken'], $answer['accessToken']]);
        self::assertSame(0, $this->number("SELECT COUNT(*) FROM identity_access__users WHERE phone = '+33611000002'"), 'no account is created');
    }

    /** AC 76: the right code of a disabled owner opens nothing. */
    public function testADisabledOwnerIsRefusedAfterTheRightCode(): void
    {
        PortalUserFactory::new()->withPhone('+33611000003')->disabled()->create();
        $challengeId = $this->requestCode('+33611000003');

        self::assertSame('account_disabled', self::problemType($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $this->lastOtpCode()]), 403));
        self::assertSame(0, $this->number('SELECT COUNT(*) FROM identity_access__refresh_tokens'));
    }

    /** AC 10, AC 40, AC 11. */
    public function testWrongCodesAreCountedAndAUsedCodeIsInvalid(): void
    {
        $challengeId = $this->requestCode('+33611000004');
        $code        = $this->lastOtpCode();
        $wrong       = '000000' === $code ? '111111' : '000000';

        for ($attempt = 1; $attempt <= 4; ++$attempt) {
            self::assertSame('otp_invalid', self::problemType($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $wrong]), 422));
        }
        self::assertSame('otp_attempts_exceeded', self::problemType($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $wrong]), 422));
        self::assertSame('otp_attempts_exceeded', self::problemType($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $code]), 422), 'even the right code');
        self::assertSame(5, $this->number('SELECT attempts FROM identity_access__otp_challenges WHERE phone = ?', ['+33611000004']), 'the failures were committed');

        $challengeId = $this->requestCode('+33611000005');
        $code        = $this->lastOtpCode();
        self::json($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $code]), 200);
        self::assertSame('otp_invalid', self::problemType($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $code]), 422), 'a code is used once');
        self::assertSame('otp_invalid', self::problemType($this->post('/auth/otp/verify', ['challengeId' => '01960000-0000-7000-8000-000000000000', 'code' => '123456']), 422), 'unknown challenge');
    }

    public function testAnExpiredCodeIsRefused(): void
    {
        $challengeId = $this->requestCode('+33611000006');
        $this->connection()->executeStatement('UPDATE identity_access__otp_challenges SET expires_at = ? WHERE phone = ?', ['2020-01-01 00:00:00', '+33611000006']);

        self::assertSame('otp_expired', self::problemType($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $this->lastOtpCode()]), 422));
    }

    /** AC 43, AC 61: coverage is MobileNumberPolicy's, the code is stored as an HMAC. */
    public function testOnlyCoveredMobilesReceiveACodeStoredAsAnHmac(): void
    {
        self::assertSame('phone_not_supported', self::problemType($this->post('/auth/otp/request', ['phone' => '+447700900123']), 422));
        self::assertSame(0, $this->otpSmsCount());

        $this->requestCode('+681721234');
        $code   = $this->lastOtpCode();
        $hash   = $this->connection()->fetchOne('SELECT code_hash FROM identity_access__otp_challenges WHERE phone = ?', ['+681721234']);
        $secret = $this->browser->getContainer()->getParameter('kernel.secret');
        \assert(\is_string($secret));

        self::assertSame(hash_hmac('sha256', $code, $secret), $hash);
        self::assertNotSame(hash('sha256', $code), $hash);
    }

    /** AC 33: validation errors share one code next to the native violations. */
    public function testInvalidInputIsAValidationProblem(): void
    {
        $answer = self::json($this->post('/auth/otp/request', ['phone' => '06 12 34 56 78']), 422);

        self::assertSame(['validation_failed', 'phone'], [$answer['type'], self::firstViolationPath($answer)]);
    }

    /** AC 64: the API lives on the portal host only. */
    public function testTheApiIsNotServedOnTheClinicHost(): void
    {
        $router = $this->browser->getContainer()->get('router');
        \assert($router instanceof RouterInterface);
        $router->setContext(new RequestContext(host: 'clinic.kiveto.local'));

        $this->expectException(ResourceNotFoundException::class);
        $router->match('/api/portal/v1/legal/terms/current');
    }
}
