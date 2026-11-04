<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Infrastructure\Webhook;

use App\Shared\Infrastructure\Deployment\Deployment;
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
        self::assertRejected(401, BrevoPayloads::request([], bearer: ''), new BrevoRequestGuard(self::IPS, '', false, Deployment::DEV));
    }

    public function testTheDevTunnelFlagSkipsOnlyTheIpCheck(): void
    {
        $guard = new BrevoRequestGuard(self::IPS, BrevoPayloads::SECRET, true, Deployment::DEV);

        $guard->check(BrevoPayloads::request([], ip: '203.0.113.7'));
        self::assertRejected(401, BrevoPayloads::request([], ip: '203.0.113.7', bearer: 'guess'), $guard);
    }

    /**
     * STAGING and DEMO are public like production: only dev may skip the IP check.
     */
    #[DataProvider('provideTheDevTunnelFlagIsRefusedOutsideDevCases')]
    public function testTheDevTunnelFlagIsRefusedOutsideDev(Deployment $deployment): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('NOTIFICATION_WEBHOOK_SKIP_IP_CHECK must only be set in dev.');

        new BrevoRequestGuard(self::IPS, BrevoPayloads::SECRET, true, $deployment);
    }

    /**
     * @return iterable<string, array{Deployment}>
     */
    public static function provideTheDevTunnelFlagIsRefusedOutsideDevCases(): iterable
    {
        yield 'staging' => [Deployment::STAGING];
        yield 'demo' => [Deployment::DEMO];
        yield 'production' => [Deployment::PRODUCTION];
    }

    /**
     * N2: Brevo's ranges are shared by all its customers — no public
     * deployment runs on the placeholder secret of `.env`, or on none.
     */
    #[DataProvider('provideAPlaceholderSecretIsRefusedOutsideDevCases')]
    public function testAPlaceholderSecretIsRefusedOutsideDev(string $secret, Deployment $deployment): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('BREVO_WEBHOOK_TOKEN must be set to a real secret outside dev.');

        new BrevoRequestGuard(self::IPS, $secret, false, $deployment);
    }

    /**
     * @return iterable<string, array{string, Deployment}>
     */
    public static function provideAPlaceholderSecretIsRefusedOutsideDevCases(): iterable
    {
        foreach (self::provideTheDevTunnelFlagIsRefusedOutsideDevCases() as $name => [$deployment]) {
            yield 'placeholder on ' . $name => ['change-me', $deployment];
            yield 'empty on ' . $name => ['', $deployment];
        }
    }

    public function testProductionRunsWithTheIpCheck(): void
    {
        $guard = new BrevoRequestGuard(self::IPS, BrevoPayloads::SECRET, false, Deployment::PRODUCTION);

        self::assertRejected(403, BrevoPayloads::request([], ip: '203.0.113.7'), $guard);
    }

    public static function guard(): BrevoRequestGuard
    {
        return new BrevoRequestGuard(self::IPS, BrevoPayloads::SECRET, false, Deployment::DEV);
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
