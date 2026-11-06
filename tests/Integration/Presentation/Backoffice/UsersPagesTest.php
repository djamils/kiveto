<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Backoffice;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Clinic\Factory\StaffProfileEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\Tests\Support\Console\SignsInToTheConsole;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * E1–E4 — the users of the console: the staff and owners lists, a clinic
 * account's sheet, its clinics, its invitation and its deactivation.
 */
final class UsersPagesTest extends WebTestCase
{
    use Factories;
    use SignsInToTheConsole;

    private const string PRADO = '01960000-0000-7000-8000-00000000d0a1';
    private const string LYON  = '01960000-0000-7000-8000-00000000d0a2';

    private KernelBrowser $browser;

    private string $claire = '';

    private string $karim = '';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        $this->createConsoleAdmin();
        $this->signInToTheConsole($this->browser);
    }

    public function testTheStaffListShowsEachStateAndFilters(): void
    {
        $this->people();
        $hugo = $this->invite('Hugo', 'Nguyen', 'h.nguyen@example.com', self::LYON);

        $crawler = $this->browser->request('GET', self::CONSOLE . '/users');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.co-tab.is-active', 'Utilisateurs');
        self::assertSame(['Dr Claire Martin', 'Hugo Nguyen', 'Karim Daoud'], $this->names($crawler));
        self::assertSame(['Actif', 'Invitation envoyée', 'Désactivé'], $crawler->filter('.co-users tbody .co-badge:not(.co-more)')->each(static fn (Crawler $b): string => trim($b->text())));
        self::assertSame(['Tous 3', 'Actifs 1', 'Invitations 1', 'Désactivés 1'], $crawler->filter('.co-filters .co-pill')->each(static fn (Crawler $p): string => preg_replace('/\s+/', ' ', trim($p->text())) ?? ''));
        self::assertSelectorTextContains('.co-users tbody tr:first-child', 'Clinique du Prado +1');
        self::assertSelectorTextContains('.co-segmented .is-active', 'Personnel clinique 3');
        self::assertSelectorTextContains('.co-segmented', 'Propriétaires (Portal) 2');

        self::assertSame(['Hugo Nguyen'], $this->names($this->browser->request('GET', self::CONSOLE . '/users?status=invited')));
        self::assertSame(['Dr Claire Martin', 'Hugo Nguyen', 'Karim Daoud'], $this->names($this->browser->request('GET', self::CONSOLE . '/users?clinic=' . self::LYON)));
        self::assertSame(['Dr Claire Martin'], $this->names($this->browser->request('GET', self::CONSOLE . '/users?clinic=' . self::PRADO)));
        self::assertSame(['Dr Claire Martin'], $this->names($this->browser->request('GET', self::CONSOLE . '/users?q=martin')));
        self::assertSame(['Dr Claire Martin'], $this->names($this->browser->request('GET', self::CONSOLE . '/users?q=06%2012%2034')), 'by phone, national form');
        self::assertSame(['Hugo Nguyen'], $this->names($this->browser->request('GET', self::CONSOLE . '/users?q=h.nguyen@')));
        self::assertSame([], $this->names($this->browser->request('GET', self::CONSOLE . '/users?q=personne&status=nimporte&clinic=inconnue')));
        self::assertSelectorTextContains('.co-users .co-empty', 'Rien ne correspond');

        $this->browser->request('GET', self::CONSOLE . '/users/' . $hugo);
        self::assertSelectorTextContains('h1', 'Hugo Nguyen');
    }

    public function testTheOwnersOfThePortal(): void
    {
        $this->people();

        $crawler = $this->browser->request('GET', self::CONSOLE . '/users?tab=portal');

        self::assertSelectorTextContains('.co-segmented .is-active', 'Propriétaires (Portal) 2');
        self::assertCount(2, $crawler->filter('.co-owners tbody tr'));
        self::assertSelectorTextContains('.co-owners', 'marie@example.com');

        $crawler = $this->browser->request('GET', self::CONSOLE . '/users?tab=portal&q=marie');
        self::assertCount(1, $crawler->filter('.co-owners tbody tr'));
    }

    public function testTheExports(): void
    {
        $this->people();

        $this->browser->request('GET', self::CONSOLE . '/users/export?status=active');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $this->content();
        self::assertStringStartsWith("\xEF\xBB\xBFNom;E-mail;Téléphone;Cliniques;Rôle;État", $csv);
        self::assertStringContainsString('"Dr Claire Martin";c.martin@example.com;+33612345678;"Clinique du Prado, Clinique de Lyon";Vétérinaire;Actif', $csv);
        self::assertStringNotContainsString('Karim', $csv);

        $this->browser->request('GET', self::CONSOLE . '/users/export?tab=portal');
        self::assertStringContainsString('marie@example.com', $this->content());

        self::assertSame(['Export de 1 comptes clinique', 'Export de 2 propriétaires'], $this->column("SELECT summary FROM audit__entries WHERE action = 'users.exported' ORDER BY occurred_at, id"));
    }

    public function testTheSheetOfAnActiveAccount(): void
    {
        $this->people();

        $this->browser->request('GET', self::CONSOLE . '/users/' . $this->claire);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Dr Claire Martin');
        self::assertSelectorTextContains('h1 .co-badge', 'Actif');
        self::assertSelectorTextContains('.co-sheet-title .co-sub', 'c.martin@example.com · Vétérinaire · 2 cliniques');
        self::assertSelectorTextContains('.co-tiles', 'FR-00001');
        self::assertSelectorTextContains('.co-tiles', '06 12 34 56 78');
        self::assertSelectorTextContains('#user-access', 'Accès aux cliniques · 2');
        self::assertCount(2, $this->browser->getCrawler()->filter('.co-access tbody tr'));
        self::assertSelectorTextContains('[aria-labelledby=user-sessions]', 'Aucune session ouverte.');
        self::assertSelectorExists('#dialog-disable');
        self::assertSelectorTextContains('#dialog-disable', 'L\'accès à Clinique du Prado et Clinique de Lyon est retiré.');
        self::assertSelectorTextContains('#dialog-disable', 'Ne compte plus comme véto facturable');
        self::assertSelectorTextContains('.co-settings .is-greyed', 'Double authentification');
    }

    public function testUnknownAndOtherAccountsHaveNoSheet(): void
    {
        $this->people();
        $owner = $this->column("SELECT BIN_TO_UUID(id) FROM identity_access__users WHERE email = 'marie@example.com'")[0];

        foreach (['01960000-0000-7000-8000-00000000dead', $owner] as $id) {
            $this->browser->request('GET', self::CONSOLE . '/users/' . $id);
            self::assertResponseStatusCodeSame(404);
        }

        $this->browser->request('POST', self::CONSOLE . '/users/' . $owner . '/disable', ['_token' => $this->token(), 'reason' => 'left']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheRolesAndClinicsOfAnAccount(): void
    {
        $this->people();
        $crawler = $this->browser->request('GET', self::CONSOLE . '/users/' . $this->claire);
        $lyon    = $this->membership($this->claire, self::LYON);

        $this->browser->submit($crawler->filter('#role-' . $lyon)->closest('form')?->form(['role' => 'MANAGER']) ?? self::fail('no role form'));
        self::assertResponseRedirects('/users/' . $this->claire);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Rôle enregistré pour Clinique de Lyon.');
        self::assertSame(['user.role_changed'], $this->column("SELECT action FROM audit__entries WHERE action = 'user.role_changed'"));

        $prado = $this->membership($this->claire, self::PRADO);
        $this->post('/users/' . $this->claire . '/memberships/' . $prado . '/remove');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Dr Claire Martin est le dernier administrateur de Clinique du Prado');

        $this->post('/users/' . $this->claire . '/memberships/' . $lyon . '/remove');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Accès à Clinique de Lyon retiré.');
        self::assertSelectorTextContains('.co-access tr.is-greyed', 'Retiré');
        self::assertSelectorTextContains('#user-access', 'Accès aux cliniques · 1');

        $this->post('/users/' . $this->claire . '/memberships/' . $lyon . '/restore');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Accès à Clinique de Lyon rétabli.');

        $this->post('/users/' . $this->karim . '/memberships/' . $lyon . '/role', ['role' => 'VETERINARY']);
        self::assertResponseStatusCodeSame(404, 'not one of his memberships');

        $this->post('/users/' . $this->claire . '/memberships/' . $lyon . '/role', ['role' => 'BOSS']);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Choisis un rôle.');
    }

    public function testAnAccountJoinsAnotherClinic(): void
    {
        $this->people();
        ClinicEntityFactory::new()->withId('01960000-0000-7000-8000-00000000d0a3')->withName('Clinique de Cassis')->withSlug('cassis-users')->create();

        $crawler = $this->browser->request('GET', self::CONSOLE . '/users/' . $this->claire);
        self::assertSame(['Clinique de Cassis'], $crawler->filter('#add-clinic option')->each(static fn (Crawler $o): string => $o->text()));

        $this->browser->submit($crawler->filter('#dialog-add form')->form(['role' => 'VETERINARY']));
        self::assertResponseRedirects('/users/' . $this->claire);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Ajouté à la clinique.');
        self::assertSelectorTextContains('#user-access', 'Accès aux cliniques · 3');

        $this->post('/users/' . $this->claire . '/memberships', ['clinic' => 'inconnue', 'role' => 'VETERINARY']);
        self::assertResponseRedirects('/users/' . $this->claire . '?dialog=add');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('#dialog-add .co-error-box', 'Choisis une clinique.');

        $this->post('/users/' . $this->claire . '/memberships', ['clinic' => self::PRADO, 'role' => '']);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('#dialog-add .co-error-box', 'Choisis un rôle.');
    }

    public function testPasswordLinkAndSessions(): void
    {
        $this->people();

        $this->post('/users/' . $this->claire . '/password-link');
        self::assertResponseRedirects('/users/' . $this->claire);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Un lien de réinitialisation part vers c.martin@example.com');

        $this->post('/users/' . $this->karim . '/password-link');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Le compte n’est pas actif');

        $this->post('/users/' . $this->claire . '/sessions/revoke');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Toutes les sessions sont révoquées.');

        $this->post('/users/' . $this->claire . '/sessions/revoke', ['session_id' => '01960000-0000-7000-8000-00000000beef']);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Session révoquée.');

        self::assertSame(['user.password_link_sent', 'user.sessions_revoked', 'user.sessions_revoked'], $this->column("SELECT action FROM audit__entries WHERE category = 'users' ORDER BY occurred_at, id"));
    }

    public function testAnAccountIsDisabledWithAReasonThenEnabled(): void
    {
        $this->people();

        $this->post('/users/' . $this->claire . '/disable', ['reason' => '']);
        self::assertResponseRedirects('/users/' . $this->claire . '?dialog=disable');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('#dialog-disable .co-error-box', 'Choisis le motif de la désactivation.');

        $this->browser->submit($this->browser->getCrawler()->filter('#dialog-disable form')->form(['reason' => 'left']));
        self::assertResponseRedirects('/users/' . $this->claire);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Compte désactivé.');
        self::assertSelectorTextContains('h1 .co-badge', 'Désactivé');
        self::assertSelectorNotExists('#dialog-disable');
        self::assertSame(['DISABLED'], $this->column('SELECT status FROM identity_access__users WHERE id = UUID_TO_BIN(?)', [$this->claire]));
        self::assertSame(['Compte désactivé · départ de la clinique'], $this->column("SELECT summary FROM audit__entries WHERE action = 'user.disabled'"));

        $this->browser->submit($this->browser->getCrawler()->filter('.co-banner.is-danger form')->form());
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Compte réactivé.');
        self::assertSame(['ACTIVE'], $this->column('SELECT status FROM identity_access__users WHERE id = UUID_TO_BIN(?)', [$this->claire]));
    }

    public function testAnInvitationIsResentCorrectedAndCancelled(): void
    {
        $this->people();
        $hugo = $this->invite('Hugo', 'Nguyen', 'h.nguyen@exemple.com', self::LYON);

        $crawler = $this->browser->request('GET', self::CONSOLE . '/users/' . $hugo);
        self::assertSelectorTextContains('h1 .co-badge', 'Invitation envoyée');
        self::assertSelectorTextContains('.co-invitation', '(dans 7 jours)');
        self::assertSelectorTextContains('.co-invitation', 'envoyé pour Clinique de Lyon');
        self::assertSelectorTextContains('[aria-labelledby=user-sessions]', 'le compte n’est pas encore activé');
        self::assertSelectorExists('.co-settings button[disabled]');

        $this->browser->submit($crawler->filter('.co-head-actions form')->form());
        self::assertResponseRedirects('/users/' . $hugo);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Invitation renvoyée.');
        self::assertCount(2, $this->invitationsTo('h.nguyen@exemple.com'));

        $this->post('/users/' . $hugo . '/invitation/email', ['email' => 'c.martin@example.com']);
        self::assertResponseRedirects('/users/' . $hugo . '?dialog=email');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('#dialog-email .co-error-box', 'Cette adresse est déjà celle d’un autre compte clinique.');

        $this->browser->submit($this->browser->getCrawler()->filter('#dialog-email form')->form(['email' => 'H.Nguyen@Example.com']));
        self::assertResponseRedirects('/users/' . $hugo);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'E-mail corrigé.');
        self::assertSelectorTextContains('.co-sheet-title', 'h.nguyen@example.com');
        self::assertCount(1, $this->invitationsTo('h.nguyen@example.com'));

        $this->browser->submit($this->browser->getCrawler()->filter('.co-invitation form')->form());
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Invitation annulée.');
        self::assertSelectorTextContains('h1 .co-badge', 'Désactivé');
        self::assertSelectorNotExists('.co-invitation');

        foreach (['/invitation/resend', '/invitation/cancel'] as $action) {
            $this->post('/users/' . $hugo . $action);
            $this->browser->followRedirect();
            self::assertSelectorTextContains('.co-flashes', 'Ce compte n’attend plus d’invitation.');
        }

        $this->post('/users/' . $hugo . '/invitation/email', ['email' => 'autre@example.com']);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'tant que l’invitation n’est pas acceptée');
    }

    public function testAnExpiredInvitationSaysSo(): void
    {
        $this->people();
        $hugo = $this->invite('Hugo', 'Nguyen', 'h.nguyen@example.com', self::LYON);
        $this->connection()->executeStatement("UPDATE identity_access__account_tokens SET created_at = created_at - INTERVAL 9 DAY, expires_at = expires_at - INTERVAL 9 DAY WHERE purpose = 'INVITATION'");

        $this->browser->request('GET', self::CONSOLE . '/users/' . $hugo);

        self::assertSelectorTextContains('h1 .co-badge', 'Invitation expirée');
        self::assertSelectorTextContains('.co-invitation', 'Le lien ne fonctionne plus');
    }

    public function testAFormWithoutItsTokenIsRefused(): void
    {
        $this->people();

        $this->browser->request('POST', self::CONSOLE . '/users/' . $this->claire . '/disable', ['_token' => 'faux', 'reason' => 'left']);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Claire: active vet, administrator of Prado, also at Lyon. Karim:
     * disabled receptionist at Lyon. Two owners on the Portal.
     */
    private function people(): void
    {
        ClinicEntityFactory::new()->withId(self::PRADO)->withName('Clinique du Prado')->withSlug('prado-users')->create();
        ClinicEntityFactory::new()->withId(self::LYON)->withName('Clinique de Lyon')->withSlug('lyon-users')->create();

        $claire       = ClinicUserFactory::new()->withEmail('c.martin@example.com')->create();
        $this->claire = $claire->getId()->toRfc4122();
        foreach ([self::PRADO => '-60 days', self::LYON => '-10 days'] as $clinic => $since) {
            $factory    = ClinicMembershipEntityFactory::new()->withClinicId($clinic)->withUserId($this->claire)->withRole(ClinicMemberRole::VETERINARY)->asEmployee()->with(['createdAt' => new \DateTimeImmutable($since), 'validFrom' => new \DateTimeImmutable($since)]);
            $membership = (self::PRADO === $clinic ? $factory->asClinicAdministrator() : $factory)->create();
            $profile    = StaffProfileEntityFactory::new()->forMembership($membership->getId())->with(['firstName' => 'Claire', 'lastName' => 'Martin', 'phoneNumber' => '+33612345678']);
            (self::PRADO === $clinic ? $profile->asDoctor() : $profile->withoutCredentials())->create();
        }

        $karim       = ClinicUserFactory::new()->withEmail('k.daoud@example.com')->disabled()->create();
        $this->karim = $karim->getId()->toRfc4122();
        $membership  = ClinicMembershipEntityFactory::new()->withClinicId(self::LYON)->withUserId($this->karim)->asReceptionist()->asEmployee()->create();
        StaffProfileEntityFactory::new()->forMembership($membership->getId())->withoutCredentials()->with(['firstName' => 'Karim', 'lastName' => 'Daoud'])->create();

        PortalUserFactory::new()->withEmail('marie@example.com')->create();
        PortalUserFactory::new()->withEmail('paul@example.com')->create();

        $this->connection()->executeStatement('UPDATE identity_access__users SET last_login_at = UTC_TIMESTAMP() - INTERVAL 1 HOUR WHERE id = ?', [Uuid::fromString($this->claire)->toBinary()]);
    }

    /**
     * Invites someone from the clinic's sheet, the way the console does; answers their id.
     */
    private function invite(string $firstName, string $lastName, string $email, string $clinicId): string
    {
        $crawler = $this->browser->request('GET', self::CONSOLE . '/clinics/' . $clinicId);
        $this->browser->submit($crawler->filter('#dialog-invite form')->form(['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email, 'role' => 'VETERINARY_ASSISTANT']));
        self::assertResponseRedirects('/clinics/' . $clinicId);

        return $this->column('SELECT BIN_TO_UUID(id) FROM identity_access__users WHERE email = ?', [mb_strtolower($email)])[0];
    }

    private function membership(string $userId, string $clinicId): string
    {
        return $this->column('SELECT BIN_TO_UUID(id) FROM clinic__clinic_memberships WHERE user_id = UUID_TO_BIN(?) AND clinic_id = UUID_TO_BIN(?)', [$userId, $clinicId])[0];
    }

    /**
     * @param array<string, string> $fields
     */
    private function post(string $path, array $fields = []): void
    {
        $this->browser->request('POST', self::CONSOLE . $path, ['_token' => $this->token()] + $fields);
    }

    private function token(): string
    {
        $crawler = $this->browser->request('GET', self::CONSOLE . '/users/' . $this->claire);

        return (string) $crawler->filter('#dialog-disable input[name="_token"]')->attr('value');
    }

    /**
     * @return list<string>
     */
    private function names(Crawler $crawler): array
    {
        return $crawler->filter('.co-users tbody .co-who b')->each(static fn (Crawler $name): string => trim($name->text()));
    }

    /**
     * @return list<string>
     */
    private function invitationsTo(string $email): array
    {
        return $this->column("SELECT BIN_TO_UUID(id) FROM notification__outbound_messages WHERE purpose = 'STAFF_INVITATION' AND recipient_address = ?", [$email]);
    }

    private function content(): string
    {
        return (string) $this->browser->getInternalResponse()->getContent();
    }

    /**
     * @param list<string> $parameters
     *
     * @return list<string>
     */
    private function column(string $sql, array $parameters = []): array
    {
        return array_map(static fn (mixed $value): string => RowAccessor::string(['v' => $value], 'v'), $this->connection()->fetchFirstColumn($sql, $parameters));
    }

    private function connection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
