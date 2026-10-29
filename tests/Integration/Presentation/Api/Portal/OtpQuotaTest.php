<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Resends and the quotas of the code-sending routes, with the real
 * database-backed limiters (no stub): each test spends its own phones and IP.
 */
final class OtpQuotaTest extends PortalApiTestCase
{
    /** AC 8, AC 42: 3 codes an hour per phone — a refused number still counts. */
    public function testAPhoneGetsThreeCodesAnHour(): void
    {
        $this->clientIp = '203.0.113.21';
        self::problemType($this->post('/auth/otp/request', ['phone' => '+447700900111']), 422);
        $this->post('/auth/otp/request', ['phone' => '+447700900111']);
        $this->post('/auth/otp/request', ['phone' => '+447700900111']);

        $refused = $this->post('/auth/otp/request', ['phone' => '+447700900111']);
        self::assertSame('otp_rate_limited', self::problemType($refused, 429));
        self::assertGreaterThan(0, (int) $refused->headers->get('Retry-After'));
    }

    /** AC 8: 30 requests an hour per client IP, whatever the phone. */
    public function testAnIpGetsThirtyCodesAnHour(): void
    {
        $this->clientIp = '203.0.113.22';
        for ($i = 0; $i < 30; ++$i) {
            $this->post('/auth/otp/request', ['phone' => \sprintf('+336130%05d', $i)]);
            self::assertSame(202, $this->browser->getResponse()->getStatusCode());
        }

        self::assertSame('otp_rate_limited', self::problemType($this->post('/auth/otp/request', ['phone' => '+33613099999']), 429));
    }

    /** AC 61: 10 codes a day per phone, resends included. */
    public function testAPhoneGetsTenCodesADay(): void
    {
        $this->clientIp = '203.0.113.23';
        $daily          = $this->limiter('limiter.portal_otp_phone_daily');
        $daily->create('+33613100001')->consume(9);

        $challengeId = $this->requestCode('+33613100001');
        $this->connection()->executeStatement('UPDATE identity_access__otp_challenges SET resend_available_at = ? WHERE phone = ?', ['2020-01-01 00:00:00', '+33613100001']);

        self::assertSame('otp_rate_limited', self::problemType($this->post('/auth/otp/' . $challengeId . '/resend', ['channel' => 'sms']), 429));
    }

    /** AC 12: a resend replaces the code, not before 30 s. */
    public function testAResendReplacesTheCode(): void
    {
        $this->clientIp = '203.0.113.24';
        PortalUserFactory::new()->withPhone('+33613200001')->create();
        $challengeId = $this->requestCode('+33613200001');
        $first       = $this->lastOtpCode();

        self::assertSame('otp_rate_limited', self::problemType($this->post('/auth/otp/' . $challengeId . '/resend', ['channel' => 'sms']), 429), 'too early');

        $this->connection()->executeStatement('UPDATE identity_access__otp_challenges SET resend_available_at = ? WHERE phone = ?', ['2020-01-01 00:00:00', '+33613200001']);
        self::assertSame($challengeId, self::json($this->post('/auth/otp/' . $challengeId . '/resend', ['channel' => 'sms']), 202)['challengeId']);
        self::assertSame(2, $this->otpSmsCount());
        $second = $this->lastOtpCode();

        if ($first !== $second) {
            self::assertSame('otp_invalid', self::problemType($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $first]), 422), 'the old code is dead');
        }
        self::assertSame('authenticated', self::json($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $second]), 200)['status']);
    }

    /** AC 53: only the SMS channel exists. */
    public function testOnlyTheSmsChannelExists(): void
    {
        $this->clientIp = '203.0.113.25';
        $challengeId    = $this->requestCode('+33613300001');

        $answer = self::json($this->post('/auth/otp/' . $challengeId . '/resend', ['channel' => 'email']), 422);
        self::assertSame(['validation_failed', 'channel'], [$answer['type'], self::firstViolationPath($answer)]);
        self::assertSame(1, $this->number('SELECT send_count FROM identity_access__otp_challenges WHERE phone = ?', ['+33613300001']));
    }

    /** AC 81: an unknown challenge answers 404 before any phone or challenge quota. */
    public function testAnUnknownChallengeSpendsNoPhoneQuota(): void
    {
        $this->clientIp = '203.0.113.26';
        $before         = $this->number('SELECT COUNT(*) FROM shared__rate_limiter_cache');

        self::assertSame('otp_challenge_not_found', self::problemType($this->post('/auth/otp/01960000-0000-7000-8000-00000000ffff/resend', ['channel' => 'sms']), 404));
        self::assertSame('otp_challenge_not_found', self::problemType($this->post('/auth/otp/not-a-uuid/resend', ['channel' => 'sms']), 404));

        self::assertSame($before + 1, $this->number('SELECT COUNT(*) FROM shared__rate_limiter_cache'), 'only the IP quota row');
    }

    /** AC 44, AC 82: beyond the budget of unknown numbers, no SMS leaves — not even on a resend. */
    public function testTheBudgetOfUnknownNumbersStopsTheSms(): void
    {
        $this->clientIp = '203.0.113.27';
        $budget         = $this->limiter('limiter.portal_otp_unknown_global')->create('global');
        while ($budget->consume()->isAccepted()) {
            // drain the hourly budget
        }

        $answer = self::json($this->post('/auth/otp/request', ['phone' => '+33613400001']), 202);
        self::assertSame(['challengeId', 'expiresAt', 'resendAvailableAt'], array_keys($answer), 'the same answer');
        self::assertSame(0, $this->otpSmsCount());
        self::assertSame(1, $this->number('SELECT sms_suppressed FROM identity_access__otp_challenges WHERE phone = ?', ['+33613400001']));

        $this->connection()->executeStatement('UPDATE identity_access__otp_challenges SET resend_available_at = ? WHERE phone = ?', ['2020-01-01 00:00:00', '+33613400001']);
        self::json($this->post('/auth/otp/' . self::string($answer['challengeId']) . '/resend', ['channel' => 'sms']), 202);
        self::assertSame(0, $this->otpSmsCount(), 'a resend cannot bypass the budget');

        PortalUserFactory::new()->withPhone('+33613400002')->create();
        $this->requestCode('+33613400002');
        self::assertSame(1, $this->otpSmsCount(), 'a known owner still receives the code');
    }

    /** AC 74: behind a trusted proxy, the per-IP quota keys on the forwarded client. */
    public function testThePerIpQuotaKeysOnTheForwardedClient(): void
    {
        Request::setTrustedProxies(['10.0.0.1'], Request::HEADER_X_FORWARDED_FOR);

        try {
            $this->clientIp = '10.0.0.1';
            $this->post('/auth/otp/request', ['phone' => '+33613500001'], ['HTTP_X_FORWARDED_FOR' => '198.51.100.1']);
            $this->post('/auth/otp/request', ['phone' => '+33613500002'], ['HTTP_X_FORWARDED_FOR' => '198.51.100.2']);
        } finally {
            Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        }

        $perIp = $this->limiter('limiter.portal_otp_ip');
        self::assertSame(29, $perIp->create('198.51.100.1')->consume(0)->getRemainingTokens());
        self::assertSame(29, $perIp->create('198.51.100.2')->consume(0)->getRemainingTokens());
        self::assertSame(30, $perIp->create('10.0.0.1')->consume(0)->getRemainingTokens(), 'the proxy itself spends nothing');
    }

    private function limiter(string $id): RateLimiterFactoryInterface
    {
        $limiter = $this->browser->getContainer()->get('test.' . $id);
        \assert($limiter instanceof RateLimiterFactoryInterface);

        return $limiter;
    }
}
