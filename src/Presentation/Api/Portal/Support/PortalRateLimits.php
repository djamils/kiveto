<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Support;

use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Shared\Problem\ApiProblem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * The quotas of the code-sending routes, consumed before the command runs:
 * a request that fails afterwards (unsupported phone…) still counts. Any
 * quota exceeded answers `429 otp_rate_limited` with `Retry-After`.
 */
final readonly class PortalRateLimits
{
    public function __construct(
        private RequestStack $requests,
        #[Autowire(service: 'limiter.portal_otp_phone')]
        private RateLimiterFactoryInterface $perPhone,
        #[Autowire(service: 'limiter.portal_otp_phone_daily')]
        private RateLimiterFactoryInterface $perPhoneDaily,
        #[Autowire(service: 'limiter.portal_otp_ip')]
        private RateLimiterFactoryInterface $perIp,
        #[Autowire(service: 'limiter.portal_invitation_otp')]
        private RateLimiterFactoryInterface $perInvitation,
        #[Autowire(service: 'limiter.portal_otp_resend')]
        private RateLimiterFactoryInterface $perChallenge,
    ) {
    }

    /** 30 an hour per client IP (behind the trusted proxies). */
    public function consumeIp(): void
    {
        self::consume($this->perIp, $this->requests->getMainRequest()?->getClientIp() ?? 'unknown');
    }

    /** 3 an hour and 10 a day per phone, sign-in and invitation codes together. */
    public function consumePhone(string $phone): void
    {
        self::consume($this->perPhone, $phone);
        self::consume($this->perPhoneDaily, $phone);
    }

    /** 3 an hour per invitation. */
    public function consumeInvitation(string $invitationId): void
    {
        self::consume($this->perInvitation, $invitationId);
    }

    /** 3 resends an hour per challenge. */
    public function consumeResend(string $challengeId): void
    {
        self::consume($this->perChallenge, $challengeId);
    }

    /**
     * @throws ApiProblem 429
     */
    private static function consume(RateLimiterFactoryInterface $factory, string $key): void
    {
        $limit = $factory->create($key)->consume();

        if (!$limit->isAccepted()) {
            $wait = max(1, $limit->getRetryAfter()->getTimestamp() - time());

            throw PortalProblemMap::problem(PortalProblemMap::OTP_RATE_LIMITED, ['Retry-After' => (string) $wait]);
        }
    }
}
