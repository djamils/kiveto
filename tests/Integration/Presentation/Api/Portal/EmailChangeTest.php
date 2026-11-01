<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;

/**
 * E-mail change through two codes (SMS, then the new address), the
 * collision rule at `/verify` (D8) and the verified e-mail as a second
 * sign-in factor (D6).
 */
final class EmailChangeTest extends PortalApiTestCase
{
    /** AC 61: SMS code, code to the new address, verified address and notice to the old one. */
    public function testAnOwnerChangesTheirEmail(): void
    {
        $this->clientIp = '203.0.113.61';
        $this->owner('+33614100001', 'old.verified@example.com', verified: true);
        $bearer = $this->bearerOf('+33614100001');

        $sms = self::json($this->post('/me/email-change/otp', [], $bearer), 202);
        self::assertSame(['challengeId', 'expiresAt', 'resendAvailableAt'], array_keys($sms));
        $request = $this->lastNotification('PORTAL_OTP');
        self::assertSame(['SMS', '+33614100001', 'email_change'], [$request->channel, $request->recipientAddress, $request->parameters['purpose'] ?? null]);
        self::assertSame(['email_change', 'user'], array_values((array) $this->connection()->fetchAssociative('SELECT purpose, subject_type FROM identity_access__otp_challenges WHERE id = UUID_TO_BIN(?)', [$sms['challengeId']])));

        $started = self::json($this->post('/me/email-change', ['challengeId' => $sms['challengeId'], 'code' => $this->lastOtpCode(), 'email' => 'New.Owner@Example.com'], $bearer), 202);
        self::assertSame(['challengeId'], array_keys($started));
        $verification = $this->lastNotification('PORTAL_EMAIL_VERIFICATION');
        self::assertSame(['EMAIL', 'new.owner@example.com'], [$verification->channel, $verification->recipientAddress]);

        $account = self::json($this->post('/me/email-change/verify', ['challengeId' => $started['challengeId'], 'code' => self::string($verification->parameters['code'] ?? null)], $bearer), 200);
        self::assertSame(['new.owner@example.com', true], [$account['email'], $account['emailVerified']]);
        self::assertSame(['id', 'firstName', 'lastName', 'email', 'emailVerified', 'maskedPhone', 'address'], array_keys($account));

        $notice = $this->lastNotification('PORTAL_EMAIL_CHANGED_NOTICE');
        self::assertSame(['EMAIL', 'old.verified@example.com', 'n***@example.com'], [$notice->channel, $notice->recipientAddress, $notice->parameters['maskedNewEmail'] ?? null]);

        $me = self::json($this->get('/me', $bearer), 200);
        self::assertIsArray($me['account']);
        self::assertSame(['new.owner@example.com', true], [$me['account']['email'], $me['account']['emailVerified']]);
    }

    /** AC 61, P6: the SMS code spends the per-phone quota of the sign-in codes. */
    public function testTheSmsCodeSpendsThePhoneQuota(): void
    {
        $this->clientIp = '203.0.113.62';
        $this->owner('+33614100002');
        $bearer = $this->bearerOf('+33614100002');

        self::json($this->post('/me/email-change/otp', [], $bearer), 202);
        self::json($this->post('/me/email-change/otp', [], $bearer), 202);
        self::assertSame('otp_rate_limited', self::problemType($this->post('/me/email-change/otp', [], $bearer), 429), 'the sign-in code was the first of three');
    }

    /** AC 206: a code of user A is refused for user B; attempts and expiry of the verification code. */
    public function testCodesAreBoundToTheirUserAndLimited(): void
    {
        $this->clientIp = '203.0.113.63';
        $this->owner('+33614100003');
        $this->owner('+33614100004');
        $alice = $this->bearerOf('+33614100003');
        $bob   = $this->bearerOf('+33614100004');

        $sms  = self::json($this->post('/me/email-change/otp', [], $alice), 202);
        $code = $this->lastOtpCode();
        self::assertSame('otp_invalid', self::problemType($this->post('/me/email-change', ['challengeId' => $sms['challengeId'], 'code' => $code, 'email' => 'bob@example.com'], $bob), 422));

        $started = self::json($this->post('/me/email-change', ['challengeId' => $sms['challengeId'], 'code' => $code, 'email' => 'alice@example.com'], $alice), 202);
        $right   = self::string($this->lastNotification('PORTAL_EMAIL_VERIFICATION')->parameters['code'] ?? null);
        $wrong   = '000000' === $right ? '111111' : '000000';

        self::assertSame('otp_invalid', self::problemType($this->post('/me/email-change/verify', ['challengeId' => $started['challengeId'], 'code' => $right], $bob), 422), 'the code of Alice for Bob');
        for ($attempt = 2; $attempt <= 4; ++$attempt) {
            self::assertSame('otp_invalid', self::problemType($this->post('/me/email-change/verify', ['challengeId' => $started['challengeId'], 'code' => $wrong], $alice), 422));
        }
        self::assertSame('otp_attempts_exceeded', self::problemType($this->post('/me/email-change/verify', ['challengeId' => $started['challengeId'], 'code' => $wrong], $alice), 422));

        $sms     = self::json($this->post('/me/email-change/otp', [], $alice), 202);
        $started = self::json($this->post('/me/email-change', ['challengeId' => $sms['challengeId'], 'code' => $this->lastOtpCode(), 'email' => 'alice@example.com'], $alice), 202);
        $this->connection()->executeStatement('UPDATE identity_access__otp_challenges SET expires_at = ? WHERE id = UUID_TO_BIN(?)', ['2020-01-01 00:00:00', $started['challengeId']]);
        self::assertSame('otp_expired', self::problemType($this->post('/me/email-change/verify', ['challengeId' => $started['challengeId'], 'code' => self::string($this->lastNotification('PORTAL_EMAIL_VERIFICATION')->parameters['code'] ?? null)], $alice), 422));

        self::assertNull($this->connection()->fetchOne('SELECT email FROM identity_access__users WHERE phone = ?', ['+33614100003']));
    }

    /** AC 207: an unverified holder loses the address; a verified one keeps it, told at /verify only. */
    public function testTheCollisionRuleAppliesOnceTheAddressIsProven(): void
    {
        $this->clientIp = '203.0.113.64';
        $this->owner('+33614100005');
        $this->owner('+33614100006', 'shared@example.com');
        $this->owner('+33614100007', 'taken@example.com', verified: true);
        $bearer = $this->bearerOf('+33614100005');

        self::assertSame(['shared@example.com', true], $this->changeTo($bearer, 'shared@example.com'));
        self::assertNull($this->connection()->fetchOne('SELECT email FROM identity_access__users WHERE phone = ?', ['+33614100006']), 'the unverified holder lost it');

        $sms     = self::json($this->post('/me/email-change/otp', [], $bearer), 202);
        $started = self::json($this->post('/me/email-change', ['challengeId' => $sms['challengeId'], 'code' => $this->lastOtpCode(), 'email' => 'taken@example.com'], $bearer), 202);
        self::assertSame('email_taken', self::problemType($this->post('/me/email-change/verify', ['challengeId' => $started['challengeId'], 'code' => self::string($this->lastNotification('PORTAL_EMAIL_VERIFICATION')->parameters['code'] ?? null)], $bearer), 422));
        self::assertSame('taken@example.com', $this->connection()->fetchOne('SELECT email FROM identity_access__users WHERE phone = ?', ['+33614100007']));
        self::assertSame('shared@example.com', $this->connection()->fetchOne('SELECT email FROM identity_access__users WHERE phone = ?', ['+33614100005']));
    }

    public function testInvalidInputIsAValidationProblem(): void
    {
        $this->clientIp = '203.0.113.65';
        $this->owner('+33614100008');
        $bearer = $this->bearerOf('+33614100008');

        $answer = self::json($this->post('/me/email-change', ['challengeId' => '01960000-0000-7000-8000-000000000000', 'code' => '123456', 'email' => 'not-an-address'], $bearer), 422);
        self::assertSame(['validation_failed', 'email'], [$answer['type'], self::firstViolationPath($answer)]);
        self::assertSame('unauthorized', self::problemType($this->post('/me/email-change/otp', []), 401));
    }

    /** AC 62: a verified e-mail receives the sign-in code; without one, nothing changes. */
    public function testASignInCodeIsResentToAVerifiedEmail(): void
    {
        $this->clientIp = '203.0.113.66';
        $this->owner('+33614100009', 'verified@example.com', verified: true);
        $challengeId = $this->requestCode('+33614100009');
        $this->allowResend($challengeId);

        self::assertSame($challengeId, self::json($this->post('/auth/otp/' . $challengeId . '/resend', ['channel' => 'email']), 202)['challengeId']);
        $emailed = $this->lastNotification('PORTAL_OTP');
        self::assertSame(['EMAIL', 'verified@example.com'], [$emailed->channel, $emailed->recipientAddress]);
        self::assertSame('authenticated', self::json($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $this->lastOtpCode()]), 200)['status']);

        $this->owner('+33614100010', 'unverified@example.com');
        $challengeId = $this->requestCode('+33614100010');
        $smsCode     = $this->lastOtpCode();
        $before      = $this->otpSmsCount();
        $this->allowResend($challengeId);

        $answer = self::json($this->post('/auth/otp/' . $challengeId . '/resend', ['channel' => 'email']), 202);
        self::assertSame(['challengeId', 'expiresAt', 'resendAvailableAt'], array_keys($answer), 'the same 202');
        self::assertSame($before, $this->otpSmsCount(), 'nothing sent');
        self::assertSame(1, $this->number('SELECT send_count FROM identity_access__otp_challenges WHERE phone = ?', ['+33614100010']));
        self::assertSame('authenticated', self::json($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $smsCode]), 200)['status'], 'the SMS code still verifies');
    }

    /** AC 208: an action code is never sent by e-mail. */
    public function testAnActionCodeIsNeverResentByEmail(): void
    {
        $this->clientIp = '203.0.113.67';
        $this->owner('+33614100011', 'verified2@example.com', verified: true);
        $bearer = $this->bearerOf('+33614100011');

        $challengeId = self::string(self::json($this->post('/me/email-change/otp', [], $bearer), 202)['challengeId']);
        $before      = $this->otpSmsCount();
        $this->allowResend($challengeId);

        self::json($this->post('/auth/otp/' . $challengeId . '/resend', ['channel' => 'email']), 202);
        self::assertSame($before, $this->otpSmsCount(), 'no e-mail, no SMS');
        self::assertSame(1, $this->number('SELECT send_count FROM identity_access__otp_challenges WHERE id = UUID_TO_BIN(?)', [$challengeId]));
    }

    private function owner(string $phone, ?string $email = null, bool $verified = false): void
    {
        PortalUserFactory::new()->withPhone($phone)->with(['email' => $email, 'emailVerifiedAt' => $verified ? new \DateTimeImmutable('-1 day') : null])->create();
    }

    /**
     * @return array<string, string>
     */
    private function bearerOf(string $phone): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer ' . self::string($this->signIn($phone)['accessToken'])];
    }

    /**
     * Runs the three steps and returns the account's e-mail and whether it is verified.
     *
     * @param array<string, string> $bearer
     *
     * @return array{mixed, mixed}
     */
    private function changeTo(array $bearer, string $email): array
    {
        $sms     = self::json($this->post('/me/email-change/otp', [], $bearer), 202);
        $started = self::json($this->post('/me/email-change', ['challengeId' => $sms['challengeId'], 'code' => $this->lastOtpCode(), 'email' => $email], $bearer), 202);
        $account = self::json($this->post('/me/email-change/verify', ['challengeId' => $started['challengeId'], 'code' => self::string($this->lastNotification('PORTAL_EMAIL_VERIFICATION')->parameters['code'] ?? null)], $bearer), 200);

        return [$account['email'], $account['emailVerified']];
    }

    private function allowResend(string $challengeId): void
    {
        $this->connection()->executeStatement('UPDATE identity_access__otp_challenges SET resend_available_at = ? WHERE id = UUID_TO_BIN(?)', ['2020-01-01 00:00:00', $challengeId]);
    }

    private function lastNotification(string $purpose): RequestNotification
    {
        $sent = array_values(array_filter($this->bus()->recorded(RequestNotification::class), static fn (RequestNotification $n): bool => $purpose === $n->purpose));
        self::assertNotSame([], $sent, $purpose . ' was not requested');

        return $sent[\count($sent) - 1];
    }
}
