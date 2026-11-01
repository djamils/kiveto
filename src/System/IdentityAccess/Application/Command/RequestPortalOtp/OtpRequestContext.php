<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RequestPortalOtp;

/**
 * Where a sign-in code request comes from, for the unknown-number SMS
 * budget: the client IP (behind the trusted proxies) and the optional
 * `X-Device-Id` header. The device id is client-controlled and forgeable:
 * a hint only, never the sole guard.
 */
final readonly class OtpRequestContext
{
    public ?string $deviceId;

    public function __construct(
        public ?string $clientIp,
        ?string $deviceId = null,
    ) {
        $deviceId       = null === $deviceId ? '' : trim($deviceId);
        $this->deviceId = '' === $deviceId ? null : $deviceId;
    }

    /**
     * The client's network: its /24 in IPv4 (`a.b.c.0/24`), its /64 in IPv6
     * (an IPv4-mapped address counts as IPv4); null without a valid IP.
     */
    public function networkKey(): ?string
    {
        $packed = null === $this->clientIp || false === filter_var($this->clientIp, \FILTER_VALIDATE_IP) ? false : inet_pton($this->clientIp);

        if (false === $packed) {
            return null;
        }

        if (16 === \strlen($packed) && str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) {
            $packed = substr($packed, 12);
        }

        if (4 === \strlen($packed)) {
            return \sprintf('%s/24', inet_ntop(substr($packed, 0, 3) . "\0"));
        }

        return \sprintf('%s/64', inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8)));
    }
}
