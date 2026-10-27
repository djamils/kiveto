<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Auth;

use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Command\RegisterInvitedUser\InvitedUserRegistration;
use App\System\IdentityAccess\Application\Command\RegisterInvitedUser\RegisterInvitedUser;
use App\System\IdentityAccess\Application\Command\SendInvitation\SendInvitation;
use App\Tests\Support\MessengerDrain;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Zenstruck\Foundry\Test\Factories;

/**
 * The public account pages of the clinic host (D11, D12 — AC 29, 29b, 31,
 * 32, 32b): reachable without a session, the secret kept out of the address
 * bar, CSRF on every form, the forgot-password form rate limited, and a
 * reset that logs out every other session.
 */
final class PublicAccountPagesTest extends WebTestCase
{
    use Factories;

    private const string HOST     = 'http://clinic.kiveto.local';
    private const string CLINIC_A = '01960000-0000-7000-8000-00000000f0a1';
    private const string CLINIC_B = '01960000-0000-7000-8000-00000000f0b1';
    private const string SENT     = 'Si un compte existe pour cette adresse, un e-mail vient d’être envoyé.';

    private KernelBrowser $browser;

    /** A run-specific suffix: the rate limiters keep their counts between tests. */
    private string $run;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();
        $this->run = bin2hex(random_bytes(4));
    }

    public function testThePagesAreReachableWithoutASessionAndSendNoReferrer(): void
    {
        $this->browser->request('GET', self::HOST . '/mot-de-passe-oublie');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        self::assertSelectorTextContains('h1', 'Mot de passe oublié');

        foreach (['/reinitialiser', '/invitation'] as $path) {
            $this->browser->request('GET', self::HOST . $path . '/un-secret-inconnu');
            self::assertResponseStatusCodeSame(303);
            self::assertResponseRedirects($path);
            self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');

            $this->browser->followRedirect();
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Ce lien n’est plus valide');
        }

        $this->browser->request('GET', self::HOST . '/invitation');
        $this->browser->getRequest()->getSession()->clear();
        $this->browser->request('GET', self::HOST . '/reinitialiser');
        self::assertSelectorTextContains('h1', 'Ce lien n’est plus valide');
    }

    /**
     * AC 31: the link only on the clinic login.
     */
    public function testTheForgotLinkIsOnlyOnTheClinicLogin(): void
    {
        $this->browser->request('GET', self::HOST . '/login');
        self::assertSelectorExists('a#lOubli[href="/mot-de-passe-oublie"]');

        foreach (['http://portal.kiveto.local/login', 'http://backoffice.kiveto.local/login'] as $login) {
            $this->browser->request('GET', $login);
            self::assertResponseIsSuccessful();
            self::assertSelectorNotExists('#lOubli');
        }
    }

    /**
     * AC 31 / 32b: same answer whatever the address, processed by the worker;
     * a 6th request from one IP within 15 minutes and a 4th for one address
     * within an hour send nothing.
     */
    public function testTheForgotFormAnswersTheSameAndIsRateLimited(): void
    {
        $ip = '198.51.100.' . random_int(1, 254);

        for ($i = 1; $i <= 6; ++$i) {
            $this->forgot($i . '.' . $this->run . '@example.com', $ip);
            self::assertResponseStatusCodeSame(303);
            $this->browser->followRedirect();
            self::assertSelectorTextContains('.info', self::SENT);
        }
        self::assertCount(5, $this->queued('RequestPasswordReset'), 'the 6th request from the IP is not queued');

        $email = 'meme.' . $this->run . '@example.com';
        for ($i = 1; $i <= 4; ++$i) {
            $this->forgot($email, '203.0.113.' . random_int(1, 254));
            $this->browser->followRedirect();
            self::assertSelectorTextContains('.info', self::SENT);
        }
        self::assertCount(8, $this->queued('RequestPasswordReset'), 'the 4th request for the address is not queued');
    }

    /**
     * AC 32b: the three forms refuse a missing or forged CSRF token.
     */
    public function testTheFormsRefuseAForgedCsrfToken(): void
    {
        $this->browser->request('POST', self::HOST . '/mot-de-passe-oublie', ['email' => 'x@example.com', '_csrf_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);

        $this->browser->request('POST', self::HOST . '/reinitialiser', ['password' => 'un-mot-de-passe', 'confirmation' => 'un-mot-de-passe']);
        self::assertResponseStatusCodeSame(403);

        $this->browser->request('POST', self::HOST . '/invitation', ['password' => 'un-mot-de-passe', 'confirmation' => 'un-mot-de-passe', '_csrf_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);

        self::assertSame([], $this->queued('RequestPasswordReset'));
    }

    /**
     * AC 31: after the reset the old password no longer works, the link is
     * spent, and the session opened elsewhere is logged out.
     */
    public function testAResetLogsOutEveryOtherSession(): void
    {
        $email = 'claire.' . $this->run . '@example.com';
        ClinicUserFactory::new()->withEmail($email)->withPlainPassword('ancien-mot')->create();

        // A session opened on another computer.
        self::assertSame(200, $this->login($email, 'ancien-mot'));
        $this->browser->request('GET', self::HOST . '/login');
        self::assertResponseRedirects();
        $elsewhere = $this->sessionCookie();
        $this->browser->getCookieJar()->clear();

        $this->forgot($email, '192.0.2.' . random_int(1, 254));
        MessengerDrain::drain(self::getContainer(), ['async']);
        $link = $this->linkIn('PASSWORD_RESET', '/reinitialiser/');

        $this->browser->request('GET', $link);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('h1', 'Nouveau mot de passe');

        $this->submitNewPassword('court', 'court');
        self::assertResponseStatusCodeSame(422);
        $this->submitNewPassword('nouveau-mot', 'autre-mot');
        self::assertSelectorTextContains('#mdpAide', 'Les deux mots de passe ne correspondent pas.');

        $this->submitNewPassword('nouveau-mot', 'nouveau-mot');
        self::assertResponseRedirects('/login');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.info', 'Votre mot de passe a été modifié.');

        $this->browser->request('GET', $link);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('h1', 'Ce lien n’est plus valide', 'the link is spent');

        self::assertSame(401, $this->login($email, 'ancien-mot'));
        self::assertSame(200, $this->login($email, 'nouveau-mot'));

        // Back on the other computer: its session is gone.
        $this->browser->getCookieJar()->clear();
        $this->browser->getCookieJar()->set($elsewhere);
        $this->browser->request('GET', self::HOST . '/login');
        self::assertResponseIsSuccessful('the login page shows again: the session was closed');
    }

    /**
     * AC 29, 29b, 32: a PENDING member cannot sign in; they activate through
     * clinic A's link; clinic B's link then says the account is active.
     */
    public function testAnInvitedMemberActivatesThroughOneClinicLink(): void
    {
        $email        = 'hugo.' . $this->run . '@example.com';
        $registration = $this->command(new RegisterInvitedUser($email));
        \assert($registration instanceof InvitedUserRegistration);
        $this->command(new SendInvitation($registration->userId, self::CLINIC_A, 'Dr Claire Lambert', 'Clinique A', 'Paris', 'Hugo', 'Vétérinaire'));
        $this->command(new SendInvitation($registration->userId, self::CLINIC_B, 'Dr Paul Henry', 'Clinique B', 'Lyon', 'Hugo', 'ASV'));
        [$linkA, $linkB] = $this->linksIn('STAFF_INVITATION', '/invitation/');

        self::assertSame(401, $this->login($email, 'nimporte-quoi'), 'AC 32: a PENDING account cannot sign in');

        $this->browser->request('GET', $linkA);
        self::assertResponseRedirects('/invitation');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('h1', 'Activer mon compte');
        self::assertSelectorTextContains('.sous', $email);

        $this->submitNewPassword('mon-mot-de-passe', 'mon-mot-de-passe', '/invitation');
        self::assertResponseRedirects('/login');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.info', 'Votre compte est activé.');
        self::assertSame(200, $this->login($email, 'mon-mot-de-passe'));

        $this->browser->getCookieJar()->clear();
        $this->browser->request('GET', $linkB);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('h1', 'Votre compte est déjà actif — connectez-vous');

        $this->browser->request('GET', $linkA);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('h1', 'Ce lien n’est plus valide');
    }

    /**
     * A1: the secret kept by a link page lives 30 minutes at most in the
     * session, even when the link itself (an invitation, 72 h) is still good.
     */
    public function testTheSecretKeptInTheSessionExpires(): void
    {
        $clock = new class implements ClockInterface {
            public \DateTimeImmutable $now;

            public function now(): \DateTimeImmutable
            {
                return $this->now;
            }
        };
        $clock->now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        self::getContainer()->set(ClockInterface::class, $clock);

        $link = $this->invitationLink('ines.' . $this->run . '@example.com');
        $this->browser->request('GET', $link);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('h1', 'Activer mon compte');

        $clock->now = $clock->now->modify('+29 minutes');
        $this->browser->request('GET', self::HOST . '/invitation');
        self::assertSelectorTextContains('h1', 'Activer mon compte');

        $clock->now = $clock->now->modify('+2 minutes');
        $this->browser->request('GET', self::HOST . '/invitation');
        self::assertSelectorTextContains('h1', 'Ce lien n’est plus valide');

        $clock->now = $clock->now->modify('-10 minutes');
        $this->browser->request('GET', self::HOST . '/invitation');
        self::assertSelectorTextContains('h1', 'Ce lien n’est plus valide', 'an expired secret is forgotten, not kept');
    }

    /**
     * A1, shared workstation: someone opens their link and walks away; the
     * next person signs in on the same browser — the link is gone.
     */
    public function testASignInForgetsTheLinksOfTheSession(): void
    {
        ClinicUserFactory::new()->withEmail('suivant.' . $this->run . '@example.com')->withPlainPassword('son-mot-de-passe')->create();
        $this->browser->request('GET', $this->invitationLink('ines.' . $this->run . '@example.com'));
        $this->browser->request('GET', self::HOST . '/reinitialiser/un-autre-secret');

        self::assertSame(200, $this->login('suivant.' . $this->run . '@example.com', 'son-mot-de-passe'));
        $session = $this->browser->getRequest()->getSession();
        self::assertFalse($session->has('account.invitation_token'));
        self::assertFalse($session->has('account.reset_token'));

        $this->browser->request('GET', self::HOST . '/invitation');
        self::assertSelectorTextContains('h1', 'Ce lien n’est plus valide');
    }

    /**
     * A7: a refused form comes back with the page's script and the account line.
     */
    public function testARefusedFormKeepsItsScriptAndTheAccountLine(): void
    {
        $email = 'ines.' . $this->run . '@example.com';
        $this->browser->request('GET', $this->invitationLink($email));
        $this->browser->followRedirect();

        $this->submitNewPassword('un-mot-de-passe', 'un-autre-mot', '/invitation');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('main[data-page="clinic_invitation_submit"]');
        self::assertSelectorTextContains('.sous', $email);
        self::assertSelectorTextContains('#mdpAide', 'Les deux mots de passe ne correspondent pas.');

        $this->browser->request('POST', self::HOST . '/invitation', ['password' => 'x', 'confirmation' => 'x', '_csrf_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('.sous', $email);

        $appJs = (string) file_get_contents(\dirname(__DIR__, 4) . '/assets/app.js');
        foreach (['clinic_forgot_password_submit', 'clinic_reset_password_submit', 'clinic_invitation_submit'] as $route) {
            self::assertMatchesRegularExpression('/' . $route . ":\\s+'pages\\/auth'/", $appJs);
        }
    }

    private function invitationLink(string $email): string
    {
        $registration = $this->command(new RegisterInvitedUser($email));
        \assert($registration instanceof InvitedUserRegistration);
        $this->command(new SendInvitation($registration->userId, self::CLINIC_A, 'Dr Claire Lambert', 'Clinique A', 'Paris', 'Inès', 'Vétérinaire'));

        return $this->linkIn('STAFF_INVITATION', '/invitation/');
    }

    private function forgot(string $email, string $ip): void
    {
        $this->browser->request('GET', self::HOST . '/mot-de-passe-oublie', server: ['REMOTE_ADDR' => $ip]);
        $token = $this->browser->getCrawler()->filter('input[name="_csrf_token"]')->attr('value');
        $this->browser->request('POST', self::HOST . '/mot-de-passe-oublie', ['email' => $email, '_csrf_token' => (string) $token], server: ['REMOTE_ADDR' => $ip]);
    }

    private function submitNewPassword(string $password, string $confirmation, string $path = '/reinitialiser'): void
    {
        $this->browser->request('GET', self::HOST . $path);
        $token = $this->browser->getCrawler()->filter('input[name="_csrf_token"]')->attr('value');
        $this->browser->request('POST', self::HOST . $path, ['password' => $password, 'confirmation' => $confirmation, '_csrf_token' => (string) $token]);
    }

    private function login(string $email, string $password): int
    {
        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: json_encode(['email' => $email, 'password' => $password], \JSON_THROW_ON_ERROR));

        return $this->browser->getResponse()->getStatusCode();
    }

    private function sessionCookie(): Cookie
    {
        foreach ($this->browser->getCookieJar()->all() as $cookie) {
            if (str_contains($cookie->getName(), 'SESS')) {
                return $cookie;
            }
        }

        self::fail('No session cookie.');
    }

    private function linkIn(string $purpose, string $path): string
    {
        return $this->linksIn($purpose, $path)[0];
    }

    /**
     * The links of the e-mails of this purpose, oldest first.
     *
     * @return list<string>
     */
    private function linksIn(string $purpose, string $path): array
    {
        $links = [];

        foreach ($this->connection()->fetchFirstColumn("SELECT JSON_UNQUOTE(JSON_EXTRACT(content, '$.html')) FROM notification__outbound_messages WHERE purpose = ? ORDER BY created_at, id", [$purpose]) as $html) {
            self::assertIsString($html);
            $match = [];
            self::assertSame(1, preg_match('#http://clinic\.kiveto\.local' . preg_quote($path, '#') . '[A-Za-z0-9_-]{43}#', $html, $match));
            $links[] = $match[0] ?? '';
        }

        return $links;
    }

    private function command(object $command): mixed
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus->dispatch($command);
    }

    /**
     * @return list<mixed>
     */
    private function queued(string $needle): array
    {
        return $this->connection()->fetchFirstColumn("SELECT id FROM shared__messenger_messages WHERE queue_name = 'default' AND body LIKE ?", ['%' . $needle . '%']);
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
