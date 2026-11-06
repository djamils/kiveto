<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Backoffice;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Clinic\Factory\StaffProfileEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\Tests\Support\Console\SignsInToTheConsole;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * D1–D3 — a support session: started from the console, opened on the
 * clinic host as the person chosen, read-only, ended back on the console.
 */
final class SupportSessionsTest extends WebTestCase
{
    use Factories;
    use SignsInToTheConsole;

    private const string CLINIC_HOST = 'http://clinic.kiveto.local';
    private const string PRADO       = '01960000-0000-7000-8000-00000000f0a1';

    private KernelBrowser $browser;

    private string $claire = '';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        $this->createConsoleAdmin();
        $this->signInToTheConsole($this->browser);

        ClinicEntityFactory::new()->withId(self::PRADO)->withName('Clinique du Prado')->withSlug('prado-support')->create(['timeZone' => 'Europe/Paris']);
        $claire       = ClinicUserFactory::new()->withEmail('c.martin@example.com')->create();
        $this->claire = $claire->getId()->toRfc4122();
        $membership   = ClinicMembershipEntityFactory::new()->withClinicId(self::PRADO)->withUserId($this->claire)->withRole(ClinicMemberRole::VETERINARY)->asEmployee()->asClinicAdministrator()->create();
        StaffProfileEntityFactory::new()->forMembership($membership->getId())->asDoctor()->with(['firstName' => 'Claire', 'lastName' => 'Martin'])->create();
    }

    public function testASupportSessionFromStartToEnd(): void
    {
        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics/' . self::PRADO . '?support=1');
        self::assertSelectorExists('[data-co-dialog-open-value="dialog-support"]');
        self::assertSelectorTextContains('#dialog-support .co-people', 'Dr Claire Martin');
        self::assertSelectorExists('#dialog-support option[value=write][disabled]');
        self::assertSelectorTextContains('[aria-labelledby=sheet-support]', 'Aucune session support');

        $this->browser->submit($crawler->filter('#dialog-support form')->form(['reason' => 'Ordonnance qui ne s’imprime pas', 'minutes' => '15']));
        self::assertResponseRedirects();
        $handoff = (string) $this->browser->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#^http://clinic\.kiveto\.local/support/[A-Za-z0-9_-]{40,}$#', $handoff);
        self::assertSame(['support.session_started'], $this->column("SELECT action FROM audit__entries WHERE category = 'support'"));

        $this->browser->request('GET', $handoff);
        self::assertResponseRedirects('/dashboard');
        $this->browser->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.v-support', 'Connecté en tant que Dr Claire Martin');
        self::assertSelectorTextContains('.v-support', 'Lecture seule');
        self::assertSame([], $this->column('SELECT BIN_TO_UUID(id) FROM identity_access__user_sessions WHERE user_id = UUID_TO_BIN(?)', [$this->claire]), 'not one of her sessions');
        self::assertSame([''], $this->column("SELECT COALESCE(last_login_at, '') FROM identity_access__users WHERE email = 'c.martin@example.com'"), 'not her sign-in');

        $this->browser->request('GET', $handoff);
        self::assertResponseStatusCodeSame(410, 'the hand-off serves once');

        $this->browser->request('POST', self::CLINIC_HOST . '/settings/notifications', ['anything' => '1']);
        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('lecture seule', (string) $this->browser->getResponse()->getContent());

        $crawler = $this->browser->request('GET', self::CLINIC_HOST . '/dashboard');
        $this->browser->submit($crawler->filter('.v-support form')->form());
        self::assertResponseRedirects();
        $back = (string) $this->browser->getResponse()->headers->get('Location');
        self::assertStringStartsWith('http://backoffice.kiveto.local/clinics/' . self::PRADO . '?support_ended=', $back);

        $this->browser->request('GET', $back);
        self::assertSelectorTextContains('.co-support-ended', 'Session support terminée.');
        self::assertSelectorTextContains('.co-support-ended', 'Dr Claire Martin');
        self::assertSelectorTextContains('.co-support-ended', 'lecture seule');
        self::assertSelectorTextContains('.co-support-ended', 'aucune modification');
        self::assertSelectorTextContains('[aria-labelledby=sheet-support]', 'Ordonnance qui ne s’imprime pas');
        self::assertSame(['support.session_started', 'support.session_ended'], $this->column("SELECT action FROM audit__entries WHERE category = 'support' ORDER BY occurred_at, id"));

        $this->browser->request('GET', self::CLINIC_HOST . '/dashboard');
        self::assertResponseRedirects('/login', message: 'signed out of the clinic');
    }

    public function testAnExpiredSessionEndsAtTheNextPage(): void
    {
        $handoff = $this->start();
        $this->browser->request('GET', $handoff);
        $this->connection()->executeStatement('UPDATE identity_access__support_sessions SET expires_at = UTC_TIMESTAMP() - INTERVAL 1 MINUTE');

        $this->browser->request('GET', self::CLINIC_HOST . '/dashboard');

        self::assertResponseRedirects();
        self::assertStringContainsString('support_ended=', (string) $this->browser->getResponse()->headers->get('Location'));
        self::assertSame(['support.session_started', 'support.session_ended'], $this->column("SELECT action FROM audit__entries WHERE category = 'support' ORDER BY occurred_at, id"));
        self::assertStringContainsString('temps écoulé', $this->column("SELECT summary FROM audit__entries WHERE action = 'support.session_ended'")[0]);
    }

    public function testWhatTheConsoleRefuses(): void
    {
        $token = $this->token();

        $cases = [
            [['user' => '01960000-0000-7000-8000-00000000dead', 'reason' => 'x', 'minutes' => '15', 'mode' => 'read'], 'Choisis la personne'],
            [['user' => $this->claire, 'reason' => 'x', 'minutes' => '15', 'mode' => 'write'], 'L’écriture en session support n’est pas activée'],
            [['user' => $this->claire, 'reason' => '  ', 'minutes' => '15', 'mode' => 'read'], 'Le motif est obligatoire'],
            [['user' => $this->claire, 'reason' => 'x', 'minutes' => '45', 'mode' => 'read'], 'Durée de session non proposée'],
        ];

        foreach ($cases as [$fields, $error]) {
            $this->browser->request('POST', self::CONSOLE . '/clinics/' . self::PRADO . '/support', ['_token' => $token] + $fields);
            self::assertResponseRedirects('/clinics/' . self::PRADO . '?support=1');
            $this->browser->followRedirect();
            self::assertSelectorTextContains('#dialog-support .co-error-box', $error);
        }

        $this->browser->request('POST', self::CONSOLE . '/clinics/' . self::PRADO . '/support', ['_token' => 'faux']);
        self::assertResponseStatusCodeSame(403);

        $this->browser->request('POST', self::CONSOLE . '/clinics/01960000-0000-7000-8000-00000000dead/support', ['_token' => $token]);
        self::assertResponseStatusCodeSame(404);

        $this->browser->request('GET', self::CLINIC_HOST . '/support/' . str_repeat('x', 43));
        self::assertResponseStatusCodeSame(410);

        $this->browser->request('POST', self::CLINIC_HOST . '/support/end');
        self::assertResponseRedirects('/login');
    }

    public function testAWriteSessionCountsItsChanges(): void
    {
        $this->connection()->executeStatement("INSERT INTO feature_catalog__clinic_features (clinic_id, code, enabled, changed_at) VALUES (UUID_TO_BIN(?), 'support.write_mode', 1, UTC_TIMESTAMP())", [self::PRADO]);
        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics/' . self::PRADO);
        self::assertSelectorNotExists('#dialog-support option[value=write][disabled]');

        $this->browser->submit($crawler->filter('#dialog-support form')->form(['reason' => 'Import du stock', 'minutes' => '60', 'mode' => 'write']));
        $this->browser->request('GET', (string) $this->browser->getResponse()->headers->get('Location'));
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.v-support', 'Lecture et écriture');

        $this->browser->request('POST', self::CLINIC_HOST . '/settings/notifications', ['anything' => '1']);
        self::assertStringNotContainsString('lecture seule', (string) $this->browser->getResponse()->getContent(), 'the support session lets it through');
        self::assertSame(['1'], $this->column('SELECT writes FROM identity_access__support_sessions'));
    }

    private function start(): string
    {
        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics/' . self::PRADO);
        $this->browser->submit($crawler->filter('#dialog-support form')->form(['reason' => 'Agenda en double', 'minutes' => '15']));

        return (string) $this->browser->getResponse()->headers->get('Location');
    }

    private function token(): string
    {
        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics/' . self::PRADO);

        return (string) $crawler->filter('#dialog-support input[name="_token"]')->attr('value');
    }

    /**
     * @param list<string> $parameters
     *
     * @return list<string>
     */
    private function column(string $sql, array $parameters = []): array
    {
        return array_map(static fn (mixed $value): string => RowAccessor::string(['v' => (string) (\is_scalar($value) ? $value : '')], 'v'), $this->connection()->fetchFirstColumn($sql, $parameters));
    }

    private function connection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
