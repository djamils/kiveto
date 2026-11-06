<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Backoffice;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\IdentityAccess\Application\Port\BackofficeProfileStoreInterface;
use App\System\IdentityAccess\Domain\ValueObject\ConsoleRole;
use App\Tests\Support\Console\SignsInToTheConsole;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Foundry\Test\Factories;

/**
 * « Équipe » — the Kiveto team in the console: invited with a link that the
 * new member opens to choose a password, a role, switched off and on; what
 * support may not do.
 */
final class TeamPagesTest extends WebTestCase
{
    use Factories;
    use SignsInToTheConsole;

    private KernelBrowser $browser;

    private string $adminId;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();
        $this->adminId = $this->createConsoleAdmin();
    }

    public function testAMemberIsInvitedAndChoosesTheirPassword(): void
    {
        $this->signInToTheConsole($this->browser);

        $crawler = $this->browser->request('GET', self::CONSOLE . '/team');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.co-tab.is-active', 'Équipe');
        self::assertSame(['Djamil'], $crawler->filter('.co-team tbody .co-who b')->each(static fn (Crawler $b): string => explode(' ', trim($b->text()))[0]));

        $this->browser->submit($crawler->filter('#dialog-invite form')->form(['name' => 'Léa Martin', 'email' => 'lea@kiveto.app', 'role' => 'support']));
        self::assertResponseRedirects('/team');
        $crawler = $this->browser->followRedirect();

        self::assertSelectorTextContains('.co-team-link', 'Invitation prête pour lea@kiveto.app');
        $link = (string) $crawler->filter('.co-team-link input')->attr('value');
        self::assertStringContainsString('/invitation/', $link);
        self::assertStringContainsString('Invitation en attente', $crawler->filter('.co-team tbody')->text());

        $crawler = $this->browser->request('GET', self::CONSOLE . '/team');
        self::assertCount(0, $crawler->filter('.co-team-link'), 'the link is shown once');
        self::assertSame(['team.member_invited'], $this->column("SELECT action FROM audit__entries WHERE action LIKE 'team.%'"));

        // The new member: the admin signs out, the link is opened.
        $guest = $this->browser;
        $guest->request('GET', self::CONSOLE . '/logout');
        $guest->request('GET', self::CONSOLE . '/invitation/' . substr($link, (int) strrpos($link, '/') + 1));
        self::assertResponseRedirects('/invitation');
        $crawler = $guest->followRedirect();
        self::assertSelectorTextContains('.co-auth-sub', 'lea@kiveto.app');

        $guest->submit($crawler->filter('form')->form(['password' => 'un mot de passe solide', 'confirmation' => 'pas le même']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.co-error-box', 'ne correspondent pas');

        $guest->submit($guest->getCrawler()->filter('form')->form(['password' => 'un mot de passe solide', 'confirmation' => 'un mot de passe solide']));
        self::assertResponseRedirects('/login');
        $guest->followRedirect();
        self::assertSelectorTextContains('.co-banner', 'Ton compte est activé');

        $guest->request('GET', self::CONSOLE . '/invitation');
        self::assertSelectorTextContains('h1', 'Ce lien n’est plus valide');
    }

    public function testRolesAndSwitches(): void
    {
        $this->signInToTheConsole($this->browser);
        $other = $this->createConsoleAdmin('noe@kiveto.app', withSecondFactor: false);

        $crawler = $this->browser->request('GET', self::CONSOLE . '/team');
        $this->browser->submit($this->memberForm($crawler, $other, 'select[name=role]')->form(['role' => 'support']));
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'est maintenant support');

        $crawler = $this->browser->getCrawler();
        $this->browser->submit($this->memberForm($crawler, $this->adminId, 'select[name=role]')->form(['role' => 'support']));
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'au moins un administrateur actif');

        $crawler = $this->browser->getCrawler();
        $this->browser->submit($this->memberForm($crawler, $other, 'input[name=enabled]')->form());
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'n’a plus accès à la console');
        self::assertStringContainsString('Désactivé', $this->browser->getCrawler()->filter('.co-team tbody')->text());

        $crawler = $this->browser->getCrawler();
        $this->browser->submit($this->memberForm($crawler, $other, 'input[name=enabled]')->form());
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'retrouve la console');

        $this->browser->request('POST', self::CONSOLE . '/team/01960000-0000-7000-8000-00000000dead', ['_token' => $this->token()]);
        self::assertResponseStatusCodeSame(404);
        foreach (['/team/invite', '/team/' . $other] as $path) {
            $this->browser->request('POST', self::CONSOLE . $path, ['_token' => 'faux']);
            self::assertResponseStatusCodeSame(403);
        }

        $this->browser->request('GET', self::CONSOLE . '/team');
        $this->browser->submit($this->browser->getCrawler()->filter('#dialog-invite form')->form(['name' => 'X', 'email' => 'pas-une-adresse', 'role' => 'admin']));
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Adresse e-mail invalide.');

        self::assertSame(['team.role_changed', 'team.member_disabled', 'team.member_enabled'], $this->column("SELECT action FROM audit__entries WHERE action LIKE 'team.%' ORDER BY occurred_at, id"));
    }

    public function testSupportReadsButDoesNotChange(): void
    {
        $support  = $this->createConsoleAdmin('support@kiveto.app', 'mot-de-passe-support');
        $profiles = static::getContainer()->get(BackofficeProfileStoreInterface::class);
        \assert($profiles instanceof BackofficeProfileStoreInterface);
        $profiles->saveRole($support, ConsoleRole::SUPPORT, new \DateTimeImmutable());
        $this->signInToTheConsole($this->browser, 'support@kiveto.app', 'mot-de-passe-support');

        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics');
        self::assertResponseIsSuccessful();
        self::assertNotContains('Équipe', $crawler->filter('.co-tab')->each(static fn (Crawler $t): string => trim($t->text())));

        $this->browser->request('GET', self::CONSOLE . '/team');
        self::assertResponseStatusCodeSame(403);
        $this->browser->request('GET', self::CONSOLE . '/translations');
        self::assertResponseStatusCodeSame(403);

        $crawler = $this->browser->request('GET', self::CONSOLE . '/features/portal.online_booking');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#feature-cut');
        self::assertSelectorExists('#feature-status[disabled]');

        $this->browser->request('POST', self::CONSOLE . '/features/portal.online_booking/settings', ['_token' => (string) $crawler->filter('form[action$="/settings"] input[name=_token]')->attr('value'), 'status' => 'beta']);
        self::assertResponseStatusCodeSame(403);
        $this->browser->request('POST', self::CONSOLE . '/registry/import', ['_token' => 'any']);
        self::assertResponseStatusCodeSame(403);
    }

    /** The form of a member that holds this field (its role, or its switch). */
    private function memberForm(Crawler $crawler, string $userId, string $field): Crawler
    {
        return $crawler->filter(\sprintf('form[action$="/team/%s"]', $userId))->reduce(static fn (Crawler $form): bool => $form->filter($field)->count() > 0);
    }

    private function token(): string
    {
        $crawler = $this->browser->request('GET', self::CONSOLE . '/team');

        return (string) $crawler->filter('#dialog-invite input[name=_token]')->attr('value');
    }

    /**
     * @return list<string>
     */
    private function column(string $sql): array
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return array_map(static fn (mixed $value): string => RowAccessor::string(['v' => (string) (\is_scalar($value) ? $value : '')], 'v'), $connection->fetchFirstColumn($sql));
    }
}
