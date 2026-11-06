<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Backoffice;

use App\Tests\Support\Console\SignsInToTheConsole;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Zenstruck\Foundry\Test\Factories;

/**
 * H2 — « Mon compte »: profile, password, second factor, backup codes,
 * trusted browsers, sessions, preferences.
 */
final class AccountPageTest extends WebTestCase
{
    use Factories;
    use SignsInToTheConsole;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();
        $this->connection()->executeStatement('DELETE FROM audit__entries');

        $this->createConsoleAdmin();
        $this->signInToTheConsole($this->browser);
        self::assertResponseRedirects('/clinics');
    }

    public function testThePageShowsTheAccount(): void
    {
        $this->browser->request('GET', self::CONSOLE . '/account');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mon compte');
        self::assertInputValueSame('display_name', 'Djamil');
        self::assertSelectorTextContains('.co-settings', 'Activée');
        self::assertSelectorTextContains('.co-settings', '10 codes restants sur 10');
        self::assertSelectorTextContains('.co-sessions', 'Cette session');
        self::assertCheckboxChecked('notify_registry_failure');
        self::assertSelectorExists('.co-me-menu a[href="/account"]');
    }

    public function testTheProfileAndThePreferencesAreSaved(): void
    {
        $crawler = $this->browser->request('GET', self::CONSOLE . '/account');
        $this->browser->submit($crawler->filter('form[action="/account/profile"]')->form(['display_name' => 'Djamil Soidiki']));
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-banner.is-success', 'Profil enregistré.');
        self::assertSelectorTextContains('.co-me summary', 'DS');

        $form     = $this->browser->getCrawler()->filter('form[action="/account/preferences"]')->form();
        $registry = $form['notify_registry_failure'];
        $zone     = $form['time_zone'];
        \assert($registry instanceof ChoiceFormField && $zone instanceof ChoiceFormField);
        $registry->untick();
        $zone->select('Indian/Reunion');
        $this->browser->submit($form);
        $this->browser->followRedirect();
        self::assertCheckboxNotChecked('notify_registry_failure');
        self::assertCheckboxChecked('notify_invitation_expiry');

        self::assertSame(
            ['Djamil Soidiki', 'Indian/Reunion', 0, 1],
            array_map(static fn (mixed $v): mixed => is_numeric($v) ? (int) $v : $v, (array) $this->connection()->fetchNumeric('SELECT display_name, time_zone, notify_registry_failure, notify_invitation_expiry FROM identity_access__backoffice_profiles')),
        );
        self::assertSame(['admin.preferences_updated', 'admin.profile_updated'], $this->connection()->fetchFirstColumn("SELECT action FROM audit__entries WHERE action LIKE 'admin.%updated' ORDER BY occurred_at DESC, id DESC"));
    }

    public function testABlankNameIsRefused(): void
    {
        $crawler = $this->browser->request('GET', self::CONSOLE . '/account');
        $this->browser->submit($crawler->filter('form[action="/account/profile"]')->form(['display_name' => '   ']));
        $this->browser->followRedirect();

        self::assertSelectorTextContains('.co-banner.is-danger', 'Le nom affiché compte de 1 à 80 caractères.');
    }

    public function testChangingThePasswordSignsOut(): void
    {
        $crawler = $this->browser->request('GET', self::CONSOLE . '/account');
        $form    = $crawler->filter('form[action="/account/password"]')->form();

        $this->browser->submit($form, ['current_password' => 'faux', 'new_password' => 'nouveau-mot-de-passe', 'confirmation' => 'nouveau-mot-de-passe']);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-banner.is-danger', 'Le mot de passe actuel n’est pas le bon.');

        $this->browser->submit($form, ['current_password' => 'mot-de-passe-admin', 'new_password' => 'nouveau-mot-de-passe', 'confirmation' => 'autre']);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-banner.is-danger', 'ne correspondent pas');

        $this->browser->submit($form, ['current_password' => 'mot-de-passe-admin', 'new_password' => 'nouveau-mot-de-passe', 'confirmation' => 'nouveau-mot-de-passe']);
        $this->browser->request('GET', self::CONSOLE . '/account');
        self::assertResponseRedirects('/login', message: 'the old session is closed');

        $this->submitConsolePassword($this->browser, password: 'nouveau-mot-de-passe');
        self::assertResponseRedirects('/login/verification');
    }

    public function testRegeneratedBackupCodesReplaceTheOldOnes(): void
    {
        $old     = $this->consoleBackupCodes[0];
        $crawler = $this->browser->request('GET', self::CONSOLE . '/account');
        $this->browser->submit($crawler->filter('form[action="/account/backup-codes"]')->form());
        self::assertResponseRedirects('/account/backup-codes');

        $crawler = $this->browser->followRedirect();
        self::assertCount(10, $crawler->filter('.co-codes li'));
        self::assertStringNotContainsString($old, $crawler->filter('.co-codes')->text());
        self::assertSame(1, $this->countRows("SELECT COUNT(*) FROM audit__entries WHERE action = 'auth.backup_codes_regenerated'"));
    }

    public function testTheSecondFactorIsReconfigured(): void
    {
        $crawler = $this->browser->request('GET', self::CONSOLE . '/account/second-factor');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.co-setup-qr svg');

        $this->browser->submit($crawler->filter('form')->form(['code' => '12']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.co-error-box', 'Ce code n’est pas valide');
    }

    public function testTheOtherSessionsAndTheTrustedBrowsersAreRevoked(): void
    {
        // Another browser of the admin: an iPhone seen an hour ago, trusted.
        $this->connection()->executeStatement(
            'INSERT INTO identity_access__user_sessions (id, user_id, session_hash, user_agent, ip_address, created_at, last_seen_at)'
            . " SELECT UNHEX(REPLACE('01960000-0000-7000-8000-0000000005e1', '-', '')), id, REPEAT('a', 64), 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Version/18.0 Mobile Safari/604.1', '91.12.34.56', NOW() - INTERVAL 1 HOUR, NOW() - INTERVAL 1 HOUR"
            . " FROM identity_access__users WHERE email = 'djamil@kiveto.test'",
        );
        $this->connection()->executeStatement(
            'INSERT INTO identity_access__trusted_devices (id, user_id, token_hash, label, created_at, expires_at)'
            . " SELECT UNHEX(REPLACE('01960000-0000-7000-8000-0000000005e2', '-', '')), id, REPEAT('b', 64), 'Safari · iPhone', NOW(), NOW() + INTERVAL 30 DAY"
            . " FROM identity_access__users WHERE email = 'djamil@kiveto.test'",
        );

        $crawler = $this->browser->request('GET', self::CONSOLE . '/account');
        self::assertCount(2, $crawler->filter('.co-sessions > li'));
        self::assertSelectorTextContains('.co-sessions', 'Safari · iPhone');
        self::assertSelectorTextContains('.co-settings', '1 appareil');

        $this->browser->submit($crawler->filter('form.co-sessions-all')->form());
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-banner.is-success', 'Les autres sessions sont déconnectées.');
        self::assertCount(1, $this->browser->getCrawler()->filter('.co-sessions > li'));

        $this->browser->submit($this->browser->getCrawler()->filter('form[action="/account/trusted-devices/revoke"]')->form());
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-settings', '0 appareil');
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
