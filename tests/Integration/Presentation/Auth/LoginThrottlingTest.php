<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Auth;

use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * Password sign-in is throttled (app.login_rate_limiter): five attempts per
 * account and address in 15 minutes, counted before the password is checked,
 * given back by a successful sign-in.
 */
final class LoginThrottlingTest extends WebTestCase
{
    use Factories;

    private const string HOST     = 'http://clinic.kiveto.local';
    private const string PASSWORD = 'le-bon-mot-de-passe';

    private KernelBrowser $browser;

    /** The limiters keep their counts between runs: a fresh account and address each run. */
    private string $email;

    private string $ip;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();
        $this->email = 'tentatives.' . bin2hex(random_bytes(4)) . '@example.com';
        $this->ip    = $this->randomIp();

        ClinicUserFactory::new()->withEmail($this->email)->withPlainPassword(self::PASSWORD)->create();
    }

    public function testTheSixthAttemptIsRefusedEvenWithTheRightPassword(): void
    {
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            self::assertSame(401, $this->login('mauvais-' . $attempt, $this->ip));
        }

        self::assertSame(429, $this->login(self::PASSWORD, $this->ip));
        self::assertStringContainsString('TOO_MANY_ATTEMPTS', (string) $this->browser->getResponse()->getContent());

        self::assertSame(200, $this->login(self::PASSWORD, $this->randomIp()), 'the count is per account and address');
    }

    public function testASuccessfulSignInGivesTheAttemptsBack(): void
    {
        for ($attempt = 1; $attempt <= 4; ++$attempt) {
            self::assertSame(401, $this->login('mauvais-' . $attempt, $this->ip));
        }

        self::assertSame(200, $this->login(self::PASSWORD, $this->ip));

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            self::assertSame(401, $this->login('encore-mauvais-' . $attempt, $this->ip));
        }
    }

    private function login(string $password, string $ip): int
    {
        $this->browser->request(
            'POST',
            self::HOST . '/login',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => $ip],
            content: json_encode(['email' => $this->email, 'password' => $password], \JSON_THROW_ON_ERROR),
        );

        return $this->browser->getResponse()->getStatusCode();
    }

    private function randomIp(): string
    {
        return \sprintf('10.%d.%d.%d', random_int(0, 255), random_int(0, 255), random_int(1, 254));
    }
}
