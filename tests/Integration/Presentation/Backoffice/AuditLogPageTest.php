<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Backoffice;

use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Clinic\Factory\StaffProfileEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Audit\Application\Command\RecordAuditEntry\RecordAuditEntry;
use App\System\Audit\Domain\ValueObject\ActorKind;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\Tests\Support\Console\SignsInToTheConsole;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * H1 — the audit log of the console: the sign-ins it records, its filters,
 * its pages and its CSV export.
 */
final class AuditLogPageTest extends WebTestCase
{
    use Factories;
    use SignsInToTheConsole;

    private const string HOST   = 'http://backoffice.kiveto.local';
    private const string CLINIC = '01960000-0000-7000-8000-00000000a0c1';

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();
        $this->connection()->executeStatement('DELETE FROM audit__entries');

        $this->createConsoleAdmin();
    }

    public function testTheConsoleNeedsASignIn(): void
    {
        $this->browser->request('GET', self::HOST . '/audit');

        self::assertResponseRedirects('/login');
    }

    public function testASignInIsRecordedAndShown(): void
    {
        $this->signIn();

        $this->browser->request('GET', self::HOST . '/audit');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Journal d\'audit');
        self::assertSelectorTextContains('.co-log-entry', 'Connexion au backoffice');
        self::assertSelectorTextContains('.co-log-entry', 'admin.login');
        self::assertSelectorTextContains('.co-log-entry .co-tag', 'KIVETO');
        self::assertSelectorTextContains('.co-tab.is-active', 'Journal d’audit');
        self::assertSelectorExists('.co-tab.is-disabled');
        self::assertSelectorTextContains('.co-tab.is-disabled', 'Pré-alpha');
        self::assertSelectorTextContains('.co-env', 'Développement');
    }

    public function testARefusedSignInIsRecordedWithTheAddressTyped(): void
    {
        $this->submitConsolePassword($this->browser, password: 'faux');
        self::assertResponseRedirects('/login');

        $this->signIn();
        $this->browser->request('GET', self::HOST . '/audit?category=access');

        self::assertSelectorTextContains('.co-log-table', 'Échec de connexion au backoffice');
        self::assertSelectorTextContains('.co-log-table', 'E-mail saisi');
        self::assertSelectorTextContains('.co-log-table', 'djamil@kiveto.test');
        self::assertSelectorTextContains('.co-log-table', 'Inconnu');
    }

    public function testAClinicSignInIsRecordedWithItsClinic(): void
    {
        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique du Prado')->create();
        $vet        = ClinicUserFactory::new()->withEmail('c.martin@example.com')->withPlainPassword('mot-de-passe')->create();
        $membership = ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($vet->getId()->toRfc4122())->asManager()->asEmployee()->asClinicAdministrator()->create();
        StaffProfileEntityFactory::new()->forMembership($membership->getId())->asDoctor()->with(['firstName' => 'Claire', 'lastName' => 'Martin'])->create();

        $this->browser->request('POST', 'http://clinic.kiveto.local/login', server: $this->json(), content: '{"email":"c.martin@example.com","password":"mot-de-passe"}');
        self::assertResponseIsSuccessful();

        self::assertSame(
            [['auth.login', 'staff', 'Dr Claire Martin', 'Clinique du Prado']],
            $this->connection()->fetchAllNumeric("SELECT action, actor_kind, actor_name, clinic_name FROM audit__entries WHERE action = 'auth.login'"),
        );
    }

    public function testTheFiltersAndThePages(): void
    {
        $this->signIn();
        for ($i = 1; $i <= 13; ++$i) {
            $this->entry(AuditCategory::SUPPORT, 'Session support démarrée n° ' . $i, ActorKind::KIVETO);
        }
        $this->entry(AuditCategory::FEATURES, 'Fonctionnalité « Hospitalisation » activée', ActorKind::KIVETO, 'Clinique du Prado');
        $this->entry(AuditCategory::REGISTRY, 'Registre mis à jour : +12', ActorKind::SYSTEM);

        $this->browser->request('GET', self::HOST . '/audit?category=support');
        self::assertCount(12, $this->browser->getCrawler()->filter('.co-log-entry'));
        self::assertSelectorTextContains('.co-pager', '1–12 sur 13');
        self::assertSelectorTextContains('.co-pill.is-active', 'Support');

        $this->browser->request('GET', self::HOST . '/audit?category=support&page=2');
        self::assertCount(1, $this->browser->getCrawler()->filter('.co-log-entry'));

        $this->browser->request('GET', self::HOST . '/audit?actor=system');
        self::assertCount(1, $this->browser->getCrawler()->filter('.co-log-entry'));
        self::assertSelectorTextContains('.co-log-entry .co-tag', 'SYSTÈME');

        $this->browser->request('GET', self::HOST . '/audit?q=hospitalisation');
        self::assertCount(1, $this->browser->getCrawler()->filter('.co-log-entry'));
        self::assertSelectorTextContains('.co-log-clinic', 'Clinique du Prado');

        $this->browser->request('GET', self::HOST . '/audit?q=rien-de-tel&category=nope&period=12');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.co-empty', 'Aucune entrée');
    }

    public function testTheExportIsASemicolonCsv(): void
    {
        $this->signIn();
        $this->entry(AuditCategory::SUPPORT, 'Session support terminée', ActorKind::KIVETO, 'Clinique du Prado', [['label' => 'Durée', 'value' => '18 min']]);

        $this->browser->request('GET', self::HOST . '/audit/export?category=support');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        $csv = (string) $this->browser->getInternalResponse()->getContent();
        self::assertStringStartsWith("\xEF\xBB\xBFDate;Acteur;", $csv);
        self::assertStringContainsString('"Session support terminée";support.session_ended;Support;"Clinique du Prado";91.12.34.12;', $csv);
        self::assertStringContainsString('Durée : 18 min', $csv);
        self::assertStringNotContainsString('Connexion au backoffice', $csv, 'the filter applies');
    }

    public function testTheRemovedPagesAreGoneAndTheHomeOpensTheClinics(): void
    {
        $this->signIn();

        foreach (['/clinic-groups', '/clinic-memberships'] as $path) {
            $this->browser->request('GET', self::HOST . $path);
            self::assertResponseStatusCodeSame(404);
        }

        $this->browser->request('GET', self::HOST . '/');
        self::assertResponseRedirects('/clinics');
    }

    private function signIn(): void
    {
        $this->signInToTheConsole($this->browser);
        self::assertResponseRedirects('/clinics');
    }

    /**
     * @param list<array{label: string, value: string}> $details
     */
    private function entry(AuditCategory $category, string $summary, ActorKind $kind, ?string $clinicName = null, array $details = []): void
    {
        $bus = static::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        $bus->dispatch(new RecordAuditEntry(
            actorKind: $kind,
            actorId: ActorKind::SYSTEM === $kind ? null : '01960000-0000-7000-8000-00000000a0a1',
            actorName: ActorKind::SYSTEM === $kind ? 'Import ANMV' : 'Djamil Soidiki',
            action: match ($category) {
                AuditCategory::SUPPORT  => 'support.session_ended',
                AuditCategory::FEATURES => 'feature.enabled',
                default                 => 'registry.import_succeeded',
            },
            category: $category,
            summary: $summary,
            clinicId: null !== $clinicName ? self::CLINIC : null,
            clinicName: $clinicName,
            ipAddress: '91.12.34.12',
            details: $details,
        ));
    }

    /**
     * @return array<string, string>
     */
    private function json(): array
    {
        return ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
    }

    private function connection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
