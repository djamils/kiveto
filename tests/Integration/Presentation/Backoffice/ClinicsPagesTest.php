<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Backoffice;

use App\Context\Clinic\Application\Command\Account\OpenClinicAccount\OpenClinicAccount;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Clinic\Factory\StaffProfileEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\Tests\Support\Console\SignsInToTheConsole;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Foundry\Test\Factories;

/**
 * B1–B6, C1–C6 — the clinics of the console: the list, the creation in three
 * steps, the sheet, its edit panel, its invitations and its suspension.
 */
final class ClinicsPagesTest extends WebTestCase
{
    use Factories;
    use SignsInToTheConsole;

    private const string PRADO  = '01960000-0000-7000-8000-00000000c0a1';
    private const string CASSIS = '01960000-0000-7000-8000-00000000c0a2';
    private const string LYON   = '01960000-0000-7000-8000-00000000c0a3';

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        $this->createConsoleAdmin();
        $this->signInToTheConsole($this->browser);
    }

    public function testTheFirstVisitOffersToCreateTheFirstClinic(): void
    {
        $this->browser->request('GET', self::CONSOLE . '/clinics');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.co-tab.is-active', 'Cliniques');
        self::assertSelectorTextContains('.co-empty-first', 'Aucune clinique pour l\'instant');
        self::assertSelectorTextContains('.co-sub', '0 clinique · 0 utilisateur');
    }

    public function testTheListShowsEachClinicWithItsState(): void
    {
        $this->threeClinics();

        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics');

        self::assertResponseIsSuccessful();
        self::assertSame(['Clinique du Prado', 'Clinique de Cassis', 'Clinique de Lyon'], $this->clinicNames($crawler), 'the last activity first, then by name');
        self::assertSame(['Pilote actif', 'Suspendue', 'Onboarding'], $crawler->filter('.co-clinics tbody .co-badge')->each(static fn (Crawler $b): string => trim($b->text())));
        self::assertSame(['Toutes 3', 'Pilote actif 1', 'Onboarding 1', 'Suspendues 1'], $crawler->filter('.co-filters .co-pill')->each(static fn (Crawler $p): string => preg_replace('/\s+/', ' ', trim($p->text())) ?? ''));
        self::assertStringContainsString('Activation', $crawler->filter('.co-todo')->text(), 'Lyon was created a week ago and nobody signed in');
        self::assertStringContainsString('Clinique de Lyon', $crawler->filter('.co-todo')->text());
        self::assertSame(['PR', 'CA', 'LY'], $crawler->filter('.co-clinics tbody .co-initials')->each(static fn (Crawler $m): string => trim($m->text())), 'the marks leave out « Clinique de »');
        self::assertCount(3, $crawler->filter('.co-clinics tbody .co-initials.is-tinted[style*="--co-tint: #"]'));

        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics?todo=activation');
        self::assertSelectorTextContains('.co-todo .co-pill.is-active', 'Activation 1');
        self::assertStringContainsString('Clinique de Lyon', $crawler->filter('.co-todo-list')->text(), 'a kind keeps its items');

        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics?status=suspended');
        self::assertSame(['Clinique de Cassis'], $this->clinicNames($crawler));
        self::assertSelectorTextContains('.co-pill.is-active', 'Suspendues');

        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics?q=prado');
        self::assertSame(['Clinique du Prado'], $this->clinicNames($crawler));

        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics?q=c.martin@example');
        self::assertSame(['Clinique du Prado'], $this->clinicNames($crawler), 'a member\'s e-mail finds the clinic');

        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics?q=introuvable');
        self::assertSame([], $this->clinicNames($crawler));
        self::assertSelectorTextContains('.co-clinics .co-empty', 'Rien ne correspond');

        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics?sort=name&status=nimporte');
        self::assertSame(['Clinique de Cassis', 'Clinique de Lyon', 'Clinique du Prado'], $this->clinicNames($crawler));

        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics?sort=created');
        self::assertSame(['Clinique de Lyon', 'Clinique de Cassis', 'Clinique du Prado'], $this->clinicNames($crawler));
    }

    public function testTheSheetOfAnActiveClinic(): void
    {
        $this->threeClinics();

        $this->browser->request('GET', self::CONSOLE . '/clinics/' . self::PRADO);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Clinique du Prado');
        self::assertSelectorTextContains('h1 .co-badge', 'Pilote actif');
        self::assertSelectorNotExists('#sheet-onboarding');
        self::assertSelectorTextContains('#sheet-team', 'Équipe · 1');
        self::assertSelectorTextContains('.co-table', 'Dr Claire Martin');
        self::assertSelectorTextContains('.co-table', 'c.martin@example.com');
        self::assertSelectorTextContains('.co-dl', 'Pilote (gratuit)');
        self::assertSelectorTextContains('.co-dl', '99 € HT / mois');
        self::assertSelectorExists('#dialog-suspend');
        self::assertSelectorTextContains('[aria-labelledby=sheet-danger] .is-greyed', 'Exporter les données');
    }

    public function testAnUnknownClinicIsNotFound(): void
    {
        $this->browser->request('GET', self::CONSOLE . '/clinics/01960000-0000-7000-8000-00000000dead');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheSheetOfAnOnboardingClinicListsItsSteps(): void
    {
        $this->threeClinics();

        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics/' . self::LYON);

        self::assertSelectorTextContains('#sheet-onboarding', 'Mise en route · 1 sur 5');
        self::assertSame(1, $crawler->filter('.co-steps-list li.is-done')->count());
        self::assertSelectorTextContains('.co-steps-list li.is-greyed', 'Pré-alpha');
        self::assertSelectorTextContains('#sheet-team', 'Équipe · 0');
        self::assertSelectorTextContains('.co-table .co-empty', 'Personne pour l\'instant');
    }

    public function testTheEditPanelSavesTheClinic(): void
    {
        $this->threeClinics();

        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics/' . self::PRADO . '?dialog=edit');
        self::assertSelectorExists('[data-co-dialog-open-value="dialog-edit"]');

        $this->browser->submit($crawler->filter('#dialog-edit form')->form([
            'name'              => 'Clinique vétérinaire du Prado',
            'street'            => '12 avenue du Prado',
            'postal_code'       => '13008',
            'city'              => 'Marseille',
            'phone'             => '0491000000',
            'email'             => 'contact@prado.example',
            'time_zone'         => 'Europe/Paris',
            'parallel_software' => 'vetup',
            'plan'              => 'paid',
            'pilot_ends_on'     => '',
            'internal_note'     => 'Signée au salon.',
        ]));
        self::assertResponseRedirects('/clinics/' . self::PRADO);
        $this->browser->followRedirect();

        self::assertSelectorTextContains('.co-flashes', 'Clinique enregistrée.');
        self::assertSelectorTextContains('h1', 'Clinique vétérinaire du Prado');
        self::assertSelectorTextContains('.co-tiles', '12 avenue du Prado, 13008 Marseille');
        self::assertSelectorTextContains('.co-tiles', '04 91 00 00 00');
        self::assertSelectorTextContains('.co-tiles', 'Vetup');
        self::assertSelectorTextContains('.co-dl', 'Payant');
        self::assertSelectorTextContains('#edit-note', 'Signée au salon.');
        self::assertSame([['clinic.updated', 'Clinique vétérinaire du Prado']], $this->audit('clinic.updated'));
    }

    public function testAWrongPilotEndIsShownInThePanel(): void
    {
        $this->threeClinics();

        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics/' . self::PRADO);
        $this->browser->submit($crawler->filter('#dialog-edit form')->form(['plan' => 'pilot', 'pilot_ends_on' => '2027-02-30']));

        self::assertResponseRedirects('/clinics/' . self::PRADO . '?dialog=edit');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('#dialog-edit .co-error-box', '« 2027-02-30 » n’est pas une date');
        self::assertSelectorNotExists('.co-flashes');

        $this->browser->submit($this->browser->getCrawler()->filter('#dialog-edit form')->form(['phone' => '12']));
        $this->browser->followRedirect();
        self::assertSelectorTextContains('#dialog-edit .co-error-box', 'Ce numéro de téléphone n’est pas valide.');
    }

    public function testAPersonIsInvitedFromTheSheet(): void
    {
        $this->threeClinics();

        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics/' . self::LYON);
        $this->browser->submit($crawler->filter('#dialog-invite form')->form([
            'first_name' => 'Hugo',
            'last_name'  => 'Bernard',
            'email'      => 'H.Bernard@Example.com',
            'role'       => 'VETERINARY',
        ]));

        self::assertResponseRedirects('/clinics/' . self::LYON);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Invitation envoyée. Le lien est valable 7 jours.');
        self::assertSelectorTextContains('#sheet-team', 'Équipe · 1');
        self::assertSelectorTextContains('.co-table', 'Hugo Bernard');
        self::assertSelectorTextContains('.co-table', 'h.bernard@example.com');
        self::assertSelectorTextContains('#sheet-onboarding', 'Mise en route · 2 sur 5');
        self::assertSame(['L’équipe Kiveto vous invite à rejoindre Clinique de Lyon sur Kiveto'], $this->invitationSubjects());
        self::assertSame([['user.invited', 'Clinique de Lyon']], $this->audit('user.invited'));

        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics/' . self::LYON);
        $this->browser->submit($crawler->filter('.co-steps-list form')->form());
        self::assertResponseRedirects('/clinics/' . self::LYON);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', '1 invitation relancée.');
        self::assertCount(2, $this->invitationSubjects());
        self::assertSame([['user.invitation_resent', 'Clinique de Lyon']], $this->audit('user.invitation_resent'));
    }

    public function testAnIncompleteInvitationIsRefusedInTheWindow(): void
    {
        $this->threeClinics();

        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics/' . self::LYON);
        $this->browser->submit($crawler->filter('#dialog-invite form')->form(['first_name' => 'Hugo', 'last_name' => '', 'email' => 'pas-une-adresse']));

        self::assertResponseRedirects('/clinics/' . self::LYON . '?dialog=invite');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('#dialog-invite .co-error-box', 'Le prénom, le nom et une adresse e-mail valide sont nécessaires.');
        self::assertSame([], $this->invitationSubjects());
    }

    public function testNothingToResendSaysSo(): void
    {
        $this->threeClinics();

        $this->browser->request('POST', self::CONSOLE . '/clinics/' . self::PRADO . '/invitations/resend', ['_token' => $this->token(self::PRADO), 'back' => 'list']);

        self::assertResponseRedirects('/clinics');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Aucune invitation en attente.');
    }

    public function testASuspensionNeedsTheTenantTypedThenCanBeUndone(): void
    {
        $this->threeClinics();

        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics/' . self::PRADO);
        $this->browser->submit($crawler->filter('#dialog-suspend form')->form(['reason' => 'unpaid', 'confirmation' => 'pas-le-bon']));
        self::assertResponseRedirects('/clinics/' . self::PRADO . '?dialog=suspend');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('#dialog-suspend .co-error-box', 'tape « prado » pour confirmer');

        $crawler = $this->browser->getCrawler();
        $this->browser->submit($crawler->filter('#dialog-suspend form')->form(['reason' => 'unpaid', 'comment' => ' Relancée trois fois. ', 'confirmation' => 'prado']));
        self::assertResponseRedirects('/clinics/' . self::PRADO);
        $this->browser->followRedirect();

        self::assertSelectorTextContains('.co-flashes', 'Clinique suspendue.');
        self::assertSelectorTextContains('.co-suspended', 'motif : impayé');
        self::assertSelectorTextContains('h1 .co-badge', 'Suspendue');
        self::assertSelectorNotExists('#dialog-suspend');
        self::assertSame('suspended', $this->connection()->fetchOne('SELECT status FROM clinic__clinics WHERE id = UUID_TO_BIN(?)', [self::PRADO]));
        self::assertSame([['clinic.suspended', 'Clinique du Prado']], $this->audit('clinic.suspended'));
        $details = $this->connection()->fetchOne("SELECT details FROM audit__entries WHERE action = 'clinic.suspended'");
        self::assertIsString($details);
        self::assertStringContainsString('Relancée trois fois.', $details);

        $this->browser->submit($this->browser->getCrawler()->filter('.co-suspended form')->form());
        self::assertResponseRedirects('/clinics/' . self::PRADO);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Clinique réactivée.');
        self::assertSelectorNotExists('.co-suspended');
        self::assertSame('active', $this->connection()->fetchOne('SELECT status FROM clinic__clinics WHERE id = UUID_TO_BIN(?)', [self::PRADO]));
        self::assertSame([['clinic.reactivated', 'Clinique du Prado']], $this->audit('clinic.reactivated'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAFormWithoutItsTokenIsRefusedCases(): iterable
    {
        yield 'update' => ['/update'];
        yield 'invite' => ['/invite'];
        yield 'resend' => ['/invitations/resend'];
        yield 'suspend' => ['/suspend'];
        yield 'reactivate' => ['/reactivate'];
    }

    /**
     * @dataProvider provideAFormWithoutItsTokenIsRefusedCases
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideAFormWithoutItsTokenIsRefusedCases')]
    public function testAFormWithoutItsTokenIsRefused(string $action): void
    {
        $this->threeClinics();

        $this->browser->request('POST', self::CONSOLE . '/clinics/' . self::PRADO . $action, ['_token' => 'faux']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAnUnknownClinicCannotBeSuspendedOrReactivated(): void
    {
        $this->threeClinics();

        foreach (['/suspend', '/reactivate'] as $action) {
            $this->browser->request('POST', self::CONSOLE . '/clinics/01960000-0000-7000-8000-00000000dead' . $action, ['_token' => $this->token(self::PRADO)]);

            self::assertResponseStatusCodeSame(404);
        }
    }

    public function testANewClinicInThreeSteps(): void
    {
        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics/new?step=3');
        self::assertSelectorTextContains('.co-stepper li.is-current', 'Établissement', 'a later step needs the first one');

        $this->browser->submit($crawler->filter('form.co-col')->form([
            'company_siren'       => '523456788',
            'company_siret'       => '523 456 788 00018',
            'company_name'        => 'Élevage des Calanques',
            'company_naf'         => '75.00Z',
            'company_street'      => '12 Chemin des Goudes',
            'company_postal_code' => '13008',
            'company_city'        => 'Marseille',
            'company_vat'         => 'FR31523456788',
            'company_vat_check'   => 'valid',
            'name'                => 'Clinique des Goudes',
            'slug'                => 'Goudes',
            'phone'               => '0491000001',
            'email'               => 'Contact@Goudes.example',
            'time_zone'           => 'Europe/Paris',
            'parallel_software'   => 'drveto',
        ]));
        self::assertResponseRedirects('/clinics/new?step=2');
        $crawler = $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-stepper li.is-current', 'Équipe');
        self::assertSelectorTextContains('.co-dl', 'goudes');

        $form           = $crawler->filter('form.co-col')->form();
        $values         = $form->getPhpValues();
        $values['team'] = [
            ['firstName' => 'Claire', 'lastName' => 'Martin', 'email' => 'claire@goudes.example', 'role' => 'VETERINARY'],
            ['firstName' => 'Léa', 'lastName' => 'Petit', 'email' => 'lea@goudes.example', 'role' => 'VETERINARY_ASSISTANT'],
            ['firstName' => '', 'lastName' => '', 'email' => '', 'role' => 'RECEPTIONIST'],
        ];
        $this->browser->request('POST', $form->getUri(), $values);
        self::assertResponseRedirects('/clinics/new?step=3');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-stepper li.is-current', 'Fonctionnalités');
        self::assertSelectorTextContains('.co-dl', '1 × 99 € HT');
        self::assertSelectorTextContains('.co-dl', '0 € (pilote)');
        self::assertSelectorTextContains('button[type=submit]', 'Créer la clinique et envoyer 2 invitations');

        $this->browser->submit($this->browser->getCrawler()->filter('form.co-col')->form(['plan' => 'pilot']));
        self::assertResponseRedirects();
        $location = (string) $this->browser->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#^/clinics/[0-9a-f-]{36}/created$#', $location);
        $this->browser->followRedirect();

        self::assertSelectorTextContains('h1', 'Clinique des Goudes est créée');
        self::assertSame(2, $this->browser->getCrawler()->filter('.co-created-list .co-badge.is-green')->count());
        self::assertCount(2, $this->invitationSubjects());

        $id = substr($location, 9, 36);
        self::assertSame([['clinic.created', 'Clinique des Goudes']], $this->audit('clinic.created'));
        self::assertSame(
            ['pilot', 'drveto'],
            $this->connection()->fetchNumeric('SELECT plan, parallel_software FROM clinic__clinic_accounts WHERE clinic_id = UUID_TO_BIN(?)', [$id]),
        );

        $this->browser->request('GET', self::CONSOLE . $location);
        self::assertResponseRedirects('/clinics/' . $id, message: 'the confirmation is shown once');

        $this->browser->request('GET', self::CONSOLE . '/clinics/' . $id);
        self::assertSelectorTextContains('#sheet-onboarding', 'Mise en route · 2 sur 5');
        self::assertSelectorTextContains('.co-steps-list', 'par Djamil');
        self::assertSelectorTextContains('.co-tiles', 'Élevage des Calanques');
        self::assertSelectorTextContains('.co-tiles', '523 456 788 000 18');
        self::assertSelectorTextContains('.co-tiles', '12 Chemin des Goudes, 13008 Marseille');
        self::assertSelectorTextContains('.co-table', 'Claire Martin');
        self::assertSelectorTextContains('.co-table', 'Vétérinaire Oui');
        self::assertSelectorTextContains('.co-tiles', '04 91 00 00 01');
    }

    public function testTheStepsRefuseWhatIsMissing(): void
    {
        $this->threeClinics();

        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics/new');
        $this->browser->submit($crawler->filter('form.co-col')->form(['name' => '', 'slug' => 'prado', 'phone' => '12', 'email' => 'pas-une-adresse', 'same_address' => false]));
        self::assertResponseStatusCodeSame(422);
        $errors = $this->browser->getCrawler()->filter('.co-error-box')->text();
        foreach (['SIRET à 14 chiffres', 'Donne le nom affiché', 'Ce tenant est déjà pris.', 'Ce numéro de téléphone n’est pas valide.', 'Cette adresse e-mail n’est pas valide.', 'identique au siège'] as $expected) {
            self::assertStringContainsString($expected, $errors);
        }

        $this->browser->submit($this->browser->getCrawler()->filter('form.co-col')->form(['company_siret' => '52345678800018', 'name' => 'Goudes', 'slug' => 'Pas Bon', 'phone' => '', 'email' => '', 'same_address' => '1']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.co-error-box', 'Le tenant n’accepte que des minuscules');

        $this->browser->submit($this->browser->getCrawler()->filter('form.co-col')->form(['company_siret' => '52345678800018', 'name' => 'Goudes', 'slug' => 'goudes', 'same_address' => '1']));
        self::assertResponseRedirects('/clinics/new?step=2');
        $crawler = $this->browser->followRedirect();
        $uri     = $crawler->filter('form.co-col')->form()->getUri();
        $token   = $this->token();

        $cases = [
            [[], 'Ajoute au moins une personne'],
            [[['firstName' => 'Claire', 'lastName' => '', 'email' => 'claire@goudes.example', 'role' => 'VETERINARY']], 'Chaque personne a un prénom'],
            [[['firstName' => 'A', 'lastName' => 'B', 'email' => 'a@goudes.example', 'role' => 'VETERINARY'], ['firstName' => 'C', 'lastName' => 'D', 'email' => 'a@goudes.example', 'role' => 'MANAGER']], 'apparaît deux fois'],
            [[['firstName' => 'Léa', 'lastName' => 'Petit', 'email' => 'lea@goudes.example', 'role' => 'MANAGER'], 'pas une ligne'], 'Invite au moins un vétérinaire'],
        ];

        foreach ($cases as [$team, $error]) {
            $this->browser->request('POST', $uri, ['_token' => $token, 'team' => $team]);
            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains('.co-error-box', $error);
        }

        $this->browser->request('POST', self::CONSOLE . '/clinics/new?step=2', ['_token' => 'faux']);
        self::assertResponseStatusCodeSame(403);

        $this->browser->request('GET', self::CONSOLE . '/clinics/new?cancel=1');
        self::assertResponseRedirects('/clinics');
        $this->browser->request('GET', self::CONSOLE . '/clinics/new?step=2');
        self::assertSelectorTextContains('.co-stepper li.is-current', 'Établissement', 'the draft is gone');
    }

    public function testATenantTakenMeanwhileSendsBackToTheFirstStep(): void
    {
        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics/new');
        $this->browser->submit($crawler->filter('form.co-col')->form(['company_siret' => '52345678800018', 'name' => 'Goudes', 'slug' => 'goudes', 'same_address' => '1']));
        $crawler = $this->browser->followRedirect();
        $this->browser->request('POST', $crawler->filter('form.co-col')->form()->getUri(), ['_token' => $this->token(), 'team' => [['firstName' => 'Claire', 'lastName' => 'Martin', 'email' => 'claire@goudes.example', 'role' => 'VETERINARY']]]);
        $this->browser->followRedirect();

        ClinicEntityFactory::new()->withSlug('goudes')->create();
        $this->browser->submit($this->browser->getCrawler()->filter('form.co-col')->form(['plan' => 'paid']));

        self::assertResponseRedirects('/clinics/new?step=1&taken=1');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-error-box', 'Ce tenant vient d’être pris');
    }

    public function testTheLookups(): void
    {
        $this->threeClinics();

        $hits = $this->lookup('what=company&q=calanques');
        self::assertTrue($hits['available']);
        self::assertIsArray($hits['hits']);
        self::assertIsArray($hits['hits'][0]);
        self::assertSame(['Élevage des Calanques', '52345678800018', 'FR31523456788'], [$hits['hits'][0]['name'], $hits['hits'][0]['siret'], $hits['hits'][0]['vat']]);
        self::assertSame(['available' => true, 'hits' => []], $this->lookup('q=ca'), 'too short to ask');

        self::assertSame(['check' => 'valid'], $this->lookup('what=vat&q=FR31523456788'));
        self::assertSame(['check' => 'invalid'], $this->lookup('what=vat&q=FR76392107454'));
        self::assertSame(['check' => 'taken'], $this->lookup('what=tenant&q=Prado'));
        self::assertSame(['check' => 'available'], $this->lookup('what=tenant&q=goudes'));
        self::assertSame(['check' => 'invalid'], $this->lookup('what=tenant&q=pas%20bon'));
    }

    /**
     * Prado: active, one vet who signed in an hour ago. Lyon: created a week
     * ago, nobody yet. Cassis: suspended.
     */
    private function threeClinics(): void
    {
        ClinicEntityFactory::new()->withId(self::PRADO)->withName('Clinique du Prado')->withSlug('prado')->with(['createdAt' => new \DateTimeImmutable('-60 days'), 'updatedAt' => new \DateTimeImmutable('-60 days')])->create();
        ClinicEntityFactory::new()->withId(self::CASSIS)->withName('Clinique de Cassis')->withSlug('cassis')->suspended()->with(['createdAt' => new \DateTimeImmutable('-30 days'), 'updatedAt' => new \DateTimeImmutable('-30 days')])->create();
        ClinicEntityFactory::new()->withId(self::LYON)->withName('Clinique de Lyon')->withSlug('lyon')->with(['createdAt' => new \DateTimeImmutable('-7 days'), 'updatedAt' => new \DateTimeImmutable('-7 days')])->create();

        $vet        = ClinicUserFactory::new()->withEmail('c.martin@example.com')->create();
        $membership = ClinicMembershipEntityFactory::new()->withClinicId(self::PRADO)->withUserId($vet->getId()->toRfc4122())->withRole(\App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole::VETERINARY)->asEmployee()->asClinicAdministrator()->create();
        StaffProfileEntityFactory::new()->forMembership($membership->getId())->asDoctor()->with(['firstName' => 'Claire', 'lastName' => 'Martin'])->create();

        $commands = static::getContainer()->get(CommandBusInterface::class);
        \assert($commands instanceof CommandBusInterface);
        $commands->dispatch(new OpenClinicAccount(self::PRADO, 'pilot', '2027-01-31', null));
        $this->connection()->executeStatement('UPDATE clinic__clinic_accounts SET onboarded_at = UTC_TIMESTAMP() WHERE clinic_id = UUID_TO_BIN(?)', [self::PRADO]);
        $this->connection()->executeStatement('UPDATE identity_access__users SET last_login_at = UTC_TIMESTAMP() - INTERVAL 1 HOUR WHERE id = ?', [$vet->getId()->toBinary()]);
    }

    /**
     * @return list<string>
     */
    private function clinicNames(Crawler $crawler): array
    {
        return $crawler->filter('.co-clinics tbody .co-who b')->each(static fn (Crawler $name): string => trim($name->text()));
    }

    /**
     * @return list<array{string, string}>
     */
    private function audit(string $action): array
    {
        $rows = [];
        foreach ($this->connection()->fetchAllNumeric('SELECT action, clinic_name FROM audit__entries WHERE action = ?', [$action]) as $row) {
            $rows[] = [RowAccessor::string(['v' => $row[0]], 'v'), RowAccessor::string(['v' => $row[1]], 'v')];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function invitationSubjects(): array
    {
        $subjects = $this->connection()->fetchFirstColumn("SELECT JSON_UNQUOTE(JSON_EXTRACT(content, '$.subject')) FROM notification__outbound_messages WHERE purpose = 'STAFF_INVITATION'");

        return array_map(static fn (mixed $subject): string => RowAccessor::string(['v' => $subject], 'v'), $subjects);
    }

    /**
     * @return array<string, mixed>
     */
    private function lookup(string $query): array
    {
        $this->browser->request('GET', self::CONSOLE . '/clinics/new/lookup?' . $query);
        self::assertResponseIsSuccessful();
        $json = json_decode((string) $this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        \assert(\is_array($json));

        $answer = [];
        foreach ($json as $key => $value) {
            $answer[(string) $key] = $value;
        }

        return $answer;
    }

    /**
     * The form token, read off a page that holds one.
     */
    private function token(string $clinicId = self::LYON): string
    {
        $crawler = $this->browser->getCrawler();
        $field   = $crawler->filter('form.co-col input[name="_token"], #dialog-edit input[name="_token"]');

        if (0 === $field->count()) {
            $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics/' . $clinicId);
            $field   = $crawler->filter('#dialog-edit input[name="_token"]');
        }

        return (string) $field->first()->attr('value');
    }

    private function connection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
