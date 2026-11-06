<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Backoffice;

use App\Fixtures\System\IdentityAccess\Factory\BackofficeUserFactory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * The translations page, kept in the console's look and reached from the
 * account menu: search, upsert attributed to the admin, delete.
 */
final class TranslationsPageTest extends WebTestCase
{
    use Factories;

    private const string HOST = 'http://backoffice.kiveto.local';

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        BackofficeUserFactory::new()->withEmail('djamil@kiveto.test')->withPlainPassword('mot-de-passe-admin')->create();
        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"email":"djamil@kiveto.test","password":"mot-de-passe-admin"}');
        self::assertResponseIsSuccessful();
    }

    public function testAnAdminSavesFindsAndDeletesATranslation(): void
    {
        $crawler = $this->browser->request('GET', self::HOST . '/translations');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Traductions');
        self::assertSelectorExists('.co-me-menu a[href="/translations"]');
        $token = (string) $crawler->filter('form[action="/translations/upsert"] input[name="_token"]')->attr('value');

        $this->browser->request('POST', self::HOST . '/translations/upsert', [
            '_token' => $token, 'scope' => 'clinic', 'locale' => 'fr-FR', 'domain' => 'messages',
            'key'    => 'console.test.greeting', 'value' => 'Bonjour la console', 'description' => 'Essai',
        ]);
        self::assertResponseRedirects();
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-banner.is-success', 'Traduction enregistrée.');
        self::assertSelectorTextContains('.co-table', 'Bonjour la console');

        $author = $this->connection()->fetchOne("SELECT updated_by FROM translation__translation_entries WHERE translation_key = 'console.test.greeting'");
        self::assertNotEmpty($author, 'the upsert is attributed to the admin');

        $this->browser->request('POST', self::HOST . '/translations/delete', [
            '_token' => $token, 'scope' => 'clinic', 'locale' => 'fr-FR', 'domain' => 'messages', 'key' => 'console.test.greeting',
        ]);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-banner.is-success', 'Traduction supprimée.');
        self::assertSelectorTextNotContains('.co-table', 'Bonjour la console');
    }

    public function testAForgedTokenOrAnIncompleteFormIsRefused(): void
    {
        $this->browser->request('POST', self::HOST . '/translations/upsert', ['_token' => 'forged', 'scope' => 'clinic']);
        self::assertResponseStatusCodeSame(403);

        $crawler = $this->browser->request('GET', self::HOST . '/translations');
        $token   = (string) $crawler->filter('form[action="/translations/upsert"] input[name="_token"]')->attr('value');

        $this->browser->request('POST', self::HOST . '/translations/upsert', ['_token' => $token, 'scope' => 'clinic', 'locale' => 'fr-FR']);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-banner.is-danger', 'Tous les champs sont obligatoires.');

        $this->browser->request('POST', self::HOST . '/translations/delete', ['_token' => $token, 'scope' => 'clinic']);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-banner.is-danger', 'Paramètres manquants pour la suppression.');
    }

    private function connection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
