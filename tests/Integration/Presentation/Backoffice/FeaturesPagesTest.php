<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Backoffice;

use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\FeatureCatalog\Application\Query\IsFeatureEnabled\IsFeatureEnabled;
use App\Tests\Support\Console\SignsInToTheConsole;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Foundry\Test\Factories;

/**
 * F1–F3 — the feature catalogue of the console, a feature's clinics and
 * settings, « Activer pour toutes », and the features of a clinic (its
 * sheet, the clinics list, the third step of a new clinic).
 */
final class FeaturesPagesTest extends WebTestCase
{
    use Factories;
    use SignsInToTheConsole;

    private const string PRADO  = '01960000-0000-7000-8000-00000000e0a1';
    private const string LYON   = '01960000-0000-7000-8000-00000000e0a2';
    private const string CASSIS = '01960000-0000-7000-8000-00000000e0a3';

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        $this->createConsoleAdmin();
        $this->signInToTheConsole($this->browser);

        ClinicEntityFactory::new()->withId(self::PRADO)->withName('Clinique du Prado')->withSlug('prado-features')->create();
        ClinicEntityFactory::new()->withId(self::LYON)->withName('Clinique de Lyon')->withSlug('lyon-features')->create();
        ClinicEntityFactory::new()->withId(self::CASSIS)->withName('Clinique de Cassis')->withSlug('cassis-features')->suspended()->create();
    }

    public function testTheCatalogue(): void
    {
        $crawler = $this->browser->request('GET', self::CONSOLE . '/features');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.co-tab.is-active', 'Fonctionnalités');
        self::assertSelectorTextContains('.co-sub', '4 fonctionnalités activables par clinique');
        self::assertSame(
            ['Registre des stupéfiants', 'Prise de RDV en ligne', 'Rappels SMS', 'Hospitalisation', 'Facturation électronique', 'Écriture en session support'],
            $crawler->filter('.co-features tbody .co-feature-name b')->each(static fn (Crawler $b): string => $b->text()),
        );
        self::assertSame(['À venir', 'Disponible', 'Disponible', 'À venir', 'Bêta', 'Interne'], $crawler->filter('.co-features tbody .co-badge')->each(static fn (Crawler $b): string => $b->text()));
        self::assertCount(2, $crawler->filter('.co-features tr.is-greyed'));
        self::assertStringContainsString('3 / 3', $crawler->filter('.co-features tbody tr')->eq(1)->text(), 'the legacy clinics keep online booking');
        self::assertStringContainsString('3 / 3', $crawler->filter('.co-features tbody tr')->eq(4)->text(), 'and electronic invoicing');
        self::assertStringContainsString('0 / 3', $crawler->filter('.co-features tbody tr')->eq(5)->text(), 'write mode is new');
    }

    public function testAFeatureIsSwitchedForOneClinicThenForAll(): void
    {
        $this->browser->request('GET', self::CONSOLE . '/features/support.write_mode');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Écriture en session support');
        self::assertSelectorTextContains('#feature-clinics', 'Cliniques · 0 activée sur 3');

        $this->toggle('support.write_mode', self::PRADO, true);
        self::assertResponseRedirects('/features/support.write_mode');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', '« Écriture en session support » activée pour Clinique du Prado.');
        self::assertSelectorTextContains('#feature-clinics', 'Cliniques · 1 activée sur 3');
        self::assertSelectorTextContains('.co-feature-clinics', 'Djamil');
        self::assertSelectorTextContains('[aria-labelledby=feature-history]', 'Fonctionnalité « Écriture en session support » activée');

        $this->toggle('support.write_mode', self::PRADO, true);
        $this->browser->followRedirect();
        self::assertSelectorNotExists('.co-flashes', 'nothing changed, nothing said');

        $crawler = $this->browser->getCrawler();
        self::assertSame(['Clinique de Lyon'], $crawler->filter('#dialog-all .co-pick-list li > span:first-child')->each(static fn (Crawler $s): string => $s->text()), 'not the suspended clinic');
        $this->browser->submit($crawler->filter('#dialog-all form')->form());
        self::assertResponseRedirects('/features/support.write_mode');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', '« Écriture en session support » activée pour 1 clinique.');
        self::assertSelectorTextContains('#feature-clinics', 'Cliniques · 2 activées sur 3');
        self::assertSelectorNotExists('#dialog-all');

        $this->post('/features/support.write_mode/enable-all');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Toutes les cliniques l’ont déjà.');

        self::assertSame(['feature.enabled', 'feature.enabled_for_all'], $this->column("SELECT action FROM audit__entries WHERE category = 'features' ORDER BY occurred_at, id"), 'nothing to switch, nothing logged');
    }

    public function testAFeatureIsCutOffEverywhereThenRestored(): void
    {
        $this->browser->request('GET', self::CONSOLE . '/features/portal.online_booking');
        self::assertSelectorTextContains('#feature-cut', 'Coupe-circuit');

        $this->post('/features/portal.online_booking/cut', ['reason' => '  ']);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Indique pourquoi tu la coupes');

        $this->browser->submit($this->browser->getCrawler()->filter('#dialog-cut form')->form(['reason' => 'Réservations en double chez un éditeur']));
        self::assertResponseRedirects('/features/portal.online_booking');
        $crawler = $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', '« Prise de RDV en ligne » est coupée partout.');
        self::assertSelectorTextContains('.co-cut', 'Réservations en double chez un éditeur');
        self::assertSelectorTextContains('#feature-clinics', 'Cliniques · 0 activée sur 3');
        self::assertSame(['Coupée partout', 'Coupée partout', 'Coupée partout'], $crawler->filter('.co-feature-clinics tbody td:nth-child(4) .co-badge')->each(static fn (Crawler $b): string => $b->text()));
        self::assertCount(3, $crawler->filter('.co-feature-clinics tbody input[type=checkbox][disabled]'), 'no switch decides');
        self::assertSelectorNotExists('#feature-cut');

        $enabled = static::getContainer()->get(QueryBusInterface::class);
        \assert($enabled instanceof QueryBusInterface);
        self::assertFalse($enabled->ask(new IsFeatureEnabled(self::PRADO, 'portal.online_booking')), 'the apps lose it at once');

        $crawler = $this->browser->request('GET', self::CONSOLE . '/features');
        self::assertSame('Coupée partout', $crawler->filter('.co-features tbody tr')->eq(1)->filter('.co-badge')->text());

        $this->post('/features/portal.online_booking/cut', ['reason' => 'Encore']);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'déjà coupée partout');

        $this->browser->request('GET', self::CONSOLE . '/features/portal.online_booking');
        $this->browser->submit($this->browser->getCrawler()->filter('.co-cut form')->form());
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'est remise en service');
        self::assertSelectorTextContains('#feature-clinics', 'Cliniques · 3 activées sur 3', 'each clinic is back to its state of before');
        self::assertTrue($enabled->ask(new IsFeatureEnabled(self::PRADO, 'portal.online_booking')));

        $this->post('/features/portal.online_booking/restore');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'n’est pas coupée');

        self::assertSame(['feature.cut_off', 'feature.restored'], $this->column("SELECT action FROM audit__entries WHERE category = 'features' ORDER BY occurred_at, id"));
        self::assertSame(['1'], $this->column("SELECT COUNT(*) FROM audit__entries WHERE action = 'feature.cut_off' AND details LIKE ?", ['%en double chez un%']), 'the reason is in the log');
    }

    public function testAFeatureKeptForThePaidClinicsAndTheFilters(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement(
            "INSERT INTO clinic__clinic_accounts (clinic_id, plan, internal_note, created_at, updated_at) VALUES (UUID_TO_BIN(?), 'paid', '', NOW(), NOW())",
            [self::LYON],
        );

        $this->post('/features/notification.sms_reminders/settings', ['status' => 'available', 'default_on' => '1', 'audience' => 'paid']);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'réservée à : toutes les cliniques → cliniques payantes');
        self::assertSelectorTextContains('h1', 'Cliniques payantes');

        $crawler = $this->browser->getCrawler();
        self::assertSame(['Clinique de Cassis', 'Clinique de Lyon', 'Clinique du Prado'], $crawler->filter('.co-feature-clinics tbody .co-who b')->each(static fn (Crawler $b): string => $b->text()));
        self::assertSame(['Hors formule', 'Hors formule'], $crawler->filter('.co-feature-clinics tbody td:nth-child(4) .co-badge')->each(static fn (Crawler $b): string => $b->text()), 'the two pilots');

        $crawler = $this->browser->request('GET', self::CONSOLE . '/features/notification.sms_reminders?plan=paid');
        self::assertSame(['Clinique de Lyon'], $crawler->filter('.co-feature-clinics tbody .co-who b')->each(static fn (Crawler $b): string => $b->text()));

        $crawler = $this->browser->request('GET', self::CONSOLE . '/features/notification.sms_reminders?q=prad&state=off');
        self::assertSame(['Clinique du Prado'], $crawler->filter('.co-feature-clinics tbody .co-who b')->each(static fn (Crawler $b): string => $b->text()));
        self::assertSelectorTextContains('.co-pill.is-active', 'Désactivées 1');

        // Lyon (paid) is switched off for the shown clinics, then on again: the pilots are left alone.
        $this->post('/features/notification.sms_reminders/clinics', ['enabled' => '0', 'plan' => 'paid']);
        self::assertResponseRedirects('/features/notification.sms_reminders?plan=paid');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', '« Rappels SMS » désactivée pour 1 clinique.');

        $this->post('/features/notification.sms_reminders/clinics', ['enabled' => '0', 'plan' => 'paid']);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Aucune clinique à changer.');

        self::assertSame(['feature.settings_changed', 'feature.disabled_for_many'], $this->column("SELECT action FROM audit__entries WHERE category = 'features' ORDER BY occurred_at, id"));
    }

    public function testTheSettingsOfAFeature(): void
    {
        $this->browser->request('GET', self::CONSOLE . '/features/billing.electronic_invoicing');
        $this->browser->submit($this->browser->getCrawler()->filter('form[action$="/settings"]')->form(['status' => 'available', 'default_on' => '1']));

        self::assertResponseRedirects('/features/billing.electronic_invoicing');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Réglages enregistrés : statut : bêta → disponible');
        self::assertSelectorTextContains('h1 .co-badge', 'Disponible');
        self::assertSame(['Fonctionnalité « Facturation électronique » : statut : bêta → disponible, activée par défaut pour les nouvelles cliniques'], $this->column("SELECT summary FROM audit__entries WHERE action = 'feature.settings_changed'"));

        $this->browser->submit($this->browser->getCrawler()->filter('form[action$="/settings"]')->form());
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Rien n’a changé.');

        $this->post('/features/billing.electronic_invoicing/settings', ['status' => 'upcoming']);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Une fonctionnalité « À venir » ne se règle pas');
    }

    public function testAnUpcomingFeatureCannotBeSwitchedOn(): void
    {
        $this->browser->request('GET', self::CONSOLE . '/features/clinic.hospitalisation');
        self::assertSelectorTextContains('.co-banner.is-info', 'Module à venir');
        self::assertSelectorExists('.co-feature-clinics input[type=checkbox][disabled]');
        self::assertSelectorNotExists('#dialog-all');

        $this->toggle('clinic.hospitalisation', self::PRADO, true);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'ne s’active pas');

        $this->post('/features/clinic.hospitalisation/enable-all');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'ne s’active pas');
    }

    public function testUnknownFeaturesAndTokens(): void
    {
        $this->browser->request('GET', self::CONSOLE . '/features/nope.nothing');
        self::assertResponseStatusCodeSame(404);

        foreach (['/settings', '/enable-all', '/clinics/' . self::PRADO] as $action) {
            $this->post('/features/nope.nothing' . $action);
            self::assertResponseStatusCodeSame(404);

            $this->browser->request('POST', self::CONSOLE . '/features/portal.online_booking' . $action, ['_token' => 'faux']);
            self::assertResponseStatusCodeSame(403);
        }

        $this->post('/features/portal.online_booking/clinics/01960000-0000-7000-8000-00000000dead');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheFeaturesOfAClinic(): void
    {
        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics/' . self::PRADO);
        self::assertSelectorTextContains('#sheet-features', 'Fonctionnalités');
        self::assertCount(6, $crawler->filter('.co-feature-switches li'));
        self::assertCount(2, $crawler->filter('.co-feature-switches li.is-greyed'));

        $this->toggle('portal.online_booking', self::PRADO, false, 'clinic');
        self::assertResponseRedirects('/clinics/' . self::PRADO);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', '« Prise de RDV en ligne » désactivée pour Clinique du Prado.');
        self::assertSame(['0'], $this->column("SELECT enabled FROM feature_catalog__clinic_features WHERE code = 'portal.online_booking'"));

        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics');
        self::assertSelectorTextContains('.co-clinics thead', 'Fonctions');
        self::assertStringContainsString('2 / 4', $crawler->filter('.co-clinics tbody tr')->reduce(static fn (Crawler $tr): bool => str_contains($tr->text(), 'Clinique du Prado'))->text());
    }

    public function testANewClinicStartsWithTheFeaturesTicked(): void
    {
        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics/new');
        $this->browser->submit($crawler->filter('form.co-col')->form(['company_siret' => '52345678800018', 'name' => 'Goudes', 'slug' => 'goudes-features', 'same_address' => '1']));
        $crawler = $this->browser->followRedirect();
        $this->browser->request('POST', $crawler->filter('form.co-col')->form()->getUri(), [
            '_token' => (string) $crawler->filter('form.co-col input[name="_token"]')->attr('value'),
            'team'   => [['firstName' => 'Claire', 'lastName' => 'Martin', 'email' => 'claire@goudes.example', 'role' => 'VETERINARY']],
        ]);
        $crawler = $this->browser->followRedirect();

        self::assertSame(['portal.online_booking', 'notification.sms_reminders'], $crawler->filter('.co-feature-choices input:checked')->each(static fn (Crawler $i): string => (string) $i->attr('value')), 'the defaults are ticked');
        self::assertCount(2, $crawler->filter('.co-feature-choices input[disabled]'));

        $form = $crawler->filter('form.co-col')->form();
        $this->browser->request('POST', $form->getUri(), [
            '_token'   => (string) $crawler->filter('form.co-col input[name="_token"]')->attr('value'),
            'plan'     => 'pilot',
            'features' => ['portal.online_booking', 'billing.electronic_invoicing', 'clinic.hospitalisation', 'inconnue'],
        ]);
        self::assertResponseRedirects();
        $clinicId = $this->column("SELECT BIN_TO_UUID(id) FROM clinic__clinics WHERE slug = 'goudes-features'")[0];

        self::assertSame(
            ['billing.electronic_invoicing:1', 'notification.sms_reminders:0', 'portal.online_booking:1', 'support.write_mode:0'],
            $this->column("SELECT CONCAT(code, ':', enabled) FROM feature_catalog__clinic_features WHERE clinic_id = UUID_TO_BIN(?) ORDER BY code", [$clinicId]),
        );
    }

    private function toggle(string $code, string $clinicId, bool $enabled, ?string $back = null): void
    {
        $this->post('/features/' . $code . '/clinics/' . $clinicId, ['enabled' => $enabled ? '1' : '0'] + (null !== $back ? ['back' => $back] : []));
    }

    /**
     * @param array<string, string> $fields
     */
    private function post(string $path, array $fields = []): void
    {
        $crawler = $this->browser->request('GET', self::CONSOLE . '/features/portal.online_booking');
        $token   = (string) $crawler->filter('form[action$="/settings"] input[name="_token"]')->attr('value');

        $this->browser->request('POST', self::CONSOLE . $path, ['_token' => $token] + $fields);
    }

    /**
     * @param list<string> $parameters
     *
     * @return list<string>
     */
    private function column(string $sql, array $parameters = []): array
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return array_map(static fn (mixed $value): string => RowAccessor::string(['v' => (string) (\is_scalar($value) ? $value : '')], 'v'), $connection->fetchFirstColumn($sql, $parameters));
    }
}
