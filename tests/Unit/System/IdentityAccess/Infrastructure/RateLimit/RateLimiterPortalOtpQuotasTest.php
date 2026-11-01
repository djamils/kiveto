<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\RateLimit;

use App\System\IdentityAccess\Infrastructure\RateLimit\RateLimiterPortalOtpQuotas;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/** Account deletion: the per-phone quotas of the codes forget the number. */
final class RateLimiterPortalOtpQuotasTest extends TestCase
{
    public function testForgettingAPhoneResetsBothOfItsQuotas(): void
    {
        $hourly = new RateLimiterFactory(['id' => 'portal_otp_phone', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 hour'], new InMemoryStorage());
        $daily  = new RateLimiterFactory(['id' => 'portal_otp_phone_daily', 'policy' => 'sliding_window', 'limit' => 1, 'interval' => '24 hours'], new InMemoryStorage());
        $hourly->create('+33612340401')->consume();
        $daily->create('+33612340401')->consume();
        $hourly->create('+33612340402')->consume();

        new RateLimiterPortalOtpQuotas($hourly, $daily)->forgetPhone('+33612340401');

        self::assertTrue($hourly->create('+33612340401')->consume()->isAccepted());
        self::assertTrue($daily->create('+33612340401')->consume()->isAccepted());
        self::assertFalse($hourly->create('+33612340402')->consume()->isAccepted(), 'another phone keeps its count');
    }
}
