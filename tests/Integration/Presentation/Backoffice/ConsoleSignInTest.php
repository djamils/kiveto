<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Backoffice;

use App\Fixtures\System\IdentityAccess\Factory\BackofficeUserFactory;
use App\Presentation\Backoffice\Security\BackofficeAuthenticator;
use App\System\IdentityAccess\Domain\SecondFactor\Totp;
use App\System\IdentityAccess\Domain\SecondFactor\TotpSecret;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use App\Tests\Support\Console\SignsInToTheConsole;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * A1 / A2 — the console's sign-in: password, then the second factor; its
 * first configuration, the backup codes, the trusted browser, and the
 * reset of a forgotten password.
 */
final class ConsoleSignInTest extends WebTestCase
{
    use Factories;
    use SignsInToTheConsole;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();
        $this->connection()->executeStatement('DELETE FROM audit__entries');
    }

    public function testThePasswordAloneOpensNothing(): void
    {
        $this->createConsoleAdmin();

        $this->submitConsolePassword($this->browser);
        self::assertResponseRedirects('/login/verification');

        $this->browser->request('GET', self::CONSOLE . '/clinics');
        self::assertResponseRedirects('/login');

        $crawler = $this->browser->request('GET', self::CONSOLE . '/login/verification');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Double authentification');
        self::assertSelectorTextContains('.co-auth-account', 'djamil@kiveto.test');
        self::assertCount(1, $crawler->filter('input[name="trust_device"]'));
    }

    public function testTheCodeOfTheAppSignsInAndIsAudited(): void
    {
        $this->createConsoleAdmin();

        $this->signInToTheConsole($this->browser);
        self::assertResponseRedirects('/clinics');

        $this->browser->request('GET', self::CONSOLE . '/audit');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.co-log-entry', 'Connexion au backoffice');
        self::assertSelectorTextContains('.co-log-details', 'Application d’authentification');
        self::assertSelectorTextContains('.co-me summary', 'DJ');
    }

    public function testAWrongCodeIsRefusedAndFiveEndTheAttempt(): void
    {
        $this->createConsoleAdmin();
        $this->submitConsolePassword($this->browser);

        $this->submitConsoleCode($this->browser, '000000' === $this->consoleCode() ? '111111' : '000000');
        self::assertResponseRedirects('/login/verification');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-error-box', 'Ce code n’est pas valide');

        for ($i = 2; $i <= 5; ++$i) {
            $this->submitConsoleCode($this->browser, '12345' . $i);
        }
        self::assertResponseRedirects('/login');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-error-box', 'Trop de codes invalides');

        $this->browser->request('GET', self::CONSOLE . '/login/verification');
        self::assertResponseRedirects('/login');

        self::assertSame(5, $this->countRows("SELECT COUNT(*) FROM audit__entries WHERE summary LIKE '%code de vérification invalide%'"));
    }

    public function testACodeIsUsedOnce(): void
    {
        $this->createConsoleAdmin();
        $code = $this->consoleCode();

        $this->submitConsolePassword($this->browser);
        $this->submitConsoleCode($this->browser, $code);
        self::assertResponseRedirects('/clinics');

        $this->browser->request('GET', self::CONSOLE . '/logout');
        $this->submitConsolePassword($this->browser);
        $this->submitConsoleCode($this->browser, $code);
        self::assertResponseRedirects('/login/verification', message: 'the same code a second time is refused');
    }

    public function testABackupCodeReplacesTheAppOnce(): void
    {
        $this->createConsoleAdmin();
        $backup = $this->consoleBackupCodes[0];

        $this->submitConsolePassword($this->browser);
        $crawler = $this->browser->request('GET', self::CONSOLE . '/login/verification?mode=secours');
        self::assertSelectorTextContains('label[for="code"]', 'Code de secours');
        $this->browser->submit($crawler->filter('form')->form(['code' => strtolower($backup)]));
        self::assertResponseRedirects('/clinics');

        $this->browser->request('GET', self::CONSOLE . '/logout');
        $this->submitConsolePassword($this->browser);
        $this->submitConsoleCode($this->browser, $backup);
        self::assertResponseRedirects('/login/verification');
    }

    public function testATrustedBrowserSkipsTheCodeUntilRevoked(): void
    {
        $this->createConsoleAdmin();

        $this->submitConsolePassword($this->browser);
        $this->submitConsoleCode($this->browser, $this->consoleCode(), trust: true);
        self::assertResponseRedirects('/clinics');
        self::assertNotNull($this->browser->getCookieJar()->get(BackofficeAuthenticator::TRUST_COOKIE, '/', 'backoffice.kiveto.local'));

        $this->browser->request('GET', self::CONSOLE . '/logout');
        $this->submitConsolePassword($this->browser);
        self::assertResponseRedirects('/clinics', message: 'a trusted browser skips the code');

        $this->connection()->executeStatement('UPDATE identity_access__trusted_devices SET revoked_at = NOW()');
        $this->browser->request('GET', self::CONSOLE . '/logout');
        $this->submitConsolePassword($this->browser);
        self::assertResponseRedirects('/login/verification');
    }

    public function testAnAdminWithoutSecondFactorConfiguresOneFirst(): void
    {
        $this->createConsoleAdmin(withSecondFactor: false);

        $this->submitConsolePassword($this->browser);
        self::assertResponseRedirects('/login/configuration');

        $crawler = $this->browser->request('GET', self::CONSOLE . '/login/configuration');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.co-setup-qr svg');
        $secret = str_replace(' ', '', $crawler->filter('.co-setup-key b')->text());

        $this->browser->submit($crawler->filter('form')->form(['code' => '999999' === $this->codeOf($secret) ? '000000' : '999999']));
        self::assertResponseRedirects('/login/configuration');

        $crawler = $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-error-box', 'Ce code n’est pas valide');
        self::assertSame($secret, str_replace(' ', '', $crawler->filter('.co-setup-key b')->text()), 'the same key until it is proven');

        $this->browser->submit($crawler->filter('form')->form(['code' => $this->codeOf($secret)]));
        self::assertResponseRedirects('/account/backup-codes');

        $this->browser->followRedirect();
        self::assertSelectorTextContains('h1', 'Codes de secours');
        self::assertCount(10, $this->browser->getCrawler()->filter('.co-codes li'));

        $this->browser->request('GET', self::CONSOLE . '/account/backup-codes');
        self::assertResponseRedirects('/account', message: 'shown once');

        self::assertSame(1, $this->countRows("SELECT COUNT(*) FROM audit__entries WHERE action = 'auth.2fa_enabled'"));
    }

    public function testASessionWithoutSecondFactorIsClosed(): void
    {
        $this->createConsoleAdmin();
        $user = BackofficeUserFactory::repository()->findOneBy(['email' => 'djamil@kiveto.test']);
        \assert(null !== $user);

        $this->browser->loginUser(new SecurityUser($user->getId()->toRfc4122(), 'djamil@kiveto.test', UserType::BACKOFFICE, passwordFingerprint: substr(hash('sha256', $user->getPasswordHash()), 0, 16)), 'backoffice');
        $this->browser->request('GET', self::CONSOLE . '/audit');

        self::assertResponseRedirects('/login');
    }

    public function testAForgottenPasswordIsResetOnTheConsole(): void
    {
        $this->createConsoleAdmin();

        $crawler = $this->browser->request('GET', self::CONSOLE . '/mot-de-passe-oublie');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        $this->browser->submit($crawler->filter('form')->form(['email' => 'djamil@kiveto.test']));
        self::assertResponseRedirects('/mot-de-passe-oublie');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-banner', 'Si un compte existe pour cette adresse');

        $this->browser->request('GET', self::CONSOLE . '/reinitialiser/un-secret-inconnu');
        self::assertResponseRedirects('/reinitialiser');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('h1', 'Ce lien n’est plus valide');
    }

    private function codeOf(string $secret): string
    {
        return Totp::code(TotpSecret::fromBase32($secret), Totp::stepAt(new \DateTimeImmutable()));
    }

    private function countRows(string $sql): int
    {
        $count = $this->connection()->fetchOne($sql);
        \assert(is_numeric($count));

        return (int) $count;
    }

    private function connection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
