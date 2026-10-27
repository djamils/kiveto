<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Infrastructure\Webhook;

use App\System\Notification\Infrastructure\Webhook\BrevoRequestGuard;
use App\Tests\Unit\System\Notification\Support\BrevoPayloads;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * AC 8: a Brevo call needs a Brevo IP and the shared secret.
 */
final class BrevoRequestGuardTest extends TestCase
{
    private const array IPS = ['1.179.112.0/20', '172.246.240.0/20'];

    public function testABrevoCallWithTheBearerSecretPasses(): void
    {
        self::guard()->check(BrevoPayloads::request([]));

        $this->addToAssertionCount(1);
    }

    public function testTheSecretMayComeAsATokenParameter(): void
    {
        self::guard()->check(BrevoPayloads::request([], bearer: null, query: ['token' => BrevoPayloads::SECRET]));

        $this->addToAssertionCount(1);
    }

    public function testACallFromOutsideBrevoIsRejected(): void
    {
        self::assertRejected(403, BrevoPayloads::request([], ip: '203.0.113.7'));
    }

    public function testAWrongOrMissingSecretIsRejected(): void
    {
        self::assertRejected(401, BrevoPayloads::request([], bearer: 'guess'));
        self::assertRejected(401, BrevoPayloads::request([], bearer: null));
        self::assertRejected(401, BrevoPayloads::request([], bearer: null, query: ['token' => 'guess']));
        self::assertRejected(401, Request::create('/webhook/brevo_sms?token[]=x', 'POST', server: ['REMOTE_ADDR' => BrevoPayloads::BREVO_IP]));
        // The header wins over the parameter.
        self::assertRejected(401, BrevoPayloads::request([], bearer: 'guess', query: ['token' => BrevoPayloads::SECRET]));
    }

    public function testAnUnconfiguredSecretRejectsEverything(): void
    {
        self::assertRejected(401, BrevoPayloads::request([], bearer: ''), new BrevoRequestGuard(self::IPS, '', false, 'dev'));
    }

    public function testTheDevTunnelFlagSkipsOnlyTheIpCheck(): void
    {
        $guard = new BrevoRequestGuard(self::IPS, BrevoPayloads::SECRET, true, 'dev');

        $guard->check(BrevoPayloads::request([], ip: '203.0.113.7'));
        self::assertRejected(401, BrevoPayloads::request([], ip: '203.0.113.7', bearer: 'guess'), $guard);
    }

    public function testTheDevTunnelFlagIsRefusedInProduction(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('NOTIFICATION_WEBHOOK_SKIP_IP_CHECK must not be set in production.');

        new BrevoRequestGuard(self::IPS, BrevoPayloads::SECRET, true, 'prod');
    }

    /**
     * N2: Brevo's ranges are shared by all its customers — production
     * refuses to run on the placeholder secret of `.env`, or on none.
     */
    #[DataProvider('provideAPlaceholderSecretIsRefusedInProductionCases')]
    public function testAPlaceholderSecretIsRefusedInProduction(string $secret): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('BREVO_WEBHOOK_TOKEN must be set to a real secret in production.');

        new BrevoRequestGuard(self::IPS, $secret, false, 'prod');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAPlaceholderSecretIsRefusedInProductionCases(): iterable
    {
        yield 'placeholder' => ['change-me'];
        yield 'empty' => [''];
    }

    public function testProductionRunsWithTheIpCheck(): void
    {
        $guard = new BrevoRequestGuard(self::IPS, BrevoPayloads::SECRET, false, 'prod');

        self::assertRejected(403, BrevoPayloads::request([], ip: '203.0.113.7'), $guard);
    }

    public static function guard(): BrevoRequestGuard
    {
        return new BrevoRequestGuard(self::IPS, BrevoPayloads::SECRET, false, 'dev');
    }

    private static function assertRejected(int $status, Request $request, ?BrevoRequestGuard $guard = null): void
    {
        try {
            ($guard ?? self::guard())->check($request);
            self::fail('The call should have been rejected.');
        } catch (RejectWebhookException $e) {
            self::assertSame($status, $e->getStatusCode());
        }
    }
}
