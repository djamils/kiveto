<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Team;

use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Clinic\Factory\StaffProfileEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\System\IdentityAccess\Application\Port\NotificationRequesterInterface;
use App\System\IdentityAccess\Application\Port\PasswordResetRequest;
use App\System\IdentityAccess\Application\Port\StaffInvitationRequest;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * « Inviter un membre » on the team page (AC 28, 29, 30): an unknown person
 * gets a PENDING account and an e-mail, an existing Kiveto user simply
 * joins, other accounts are refused; « Renvoyer » replaces the link.
 */
final class TeamInvitationTest extends WebTestCase
{
    use Factories;

    private const string CLINIC = '01960000-0000-7000-8000-00000000e0c1';
    private const string HOST   = 'http://clinic.kiveto.local';

    private KernelBrowser $browser;
    private string $token = '';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique Montmartre')->create();
        $admin      = ClinicUserFactory::new()->withEmail('claire.admin@example.com')->withPlainPassword('mot-de-passe')->create();
        $membership = ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($admin->getId()->toRfc4122())->asManager()->asEmployee()->asClinicAdministrator()->create();
        StaffProfileEntityFactory::new()->forMembership($membership->getId())->asDoctor()->with(['firstName' => 'Claire', 'lastName' => 'Lambert'])->create();

        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"email":"claire.admin@example.com","password":"mot-de-passe"}');
        self::assertResponseIsSuccessful();

        $this->browser->request('GET', self::HOST . '/settings/team');
        self::assertResponseIsSuccessful();
        self::assertSame(1, preg_match('/&quot;token&quot;:&quot;([^&]+)&quot;|"token":"([^"]+)"/', (string) $this->browser->getResponse()->getContent(), $match));
        $this->token = '' !== $match[1] ? $match[1] : $match[2];
    }

    public function testAnUnknownPersonIsInvitedByEmail(): void
    {
        $answer = $this->invite('hugo.martin@example.com');

        self::assertSame(200, $answer['status']);
        self::assertTrue($answer['body']['invited'] ?? null);
        self::assertSame(['PENDING'], $this->column("SELECT status FROM identity_access__users WHERE email = 'hugo.martin@example.com'"));
        self::assertSame(['Dr Claire Lambert vous invite à rejoindre Clinique Montmartre sur Kiveto'], $this->column("SELECT JSON_UNQUOTE(JSON_EXTRACT(content, '$.subject')) FROM notification__outbound_messages WHERE purpose = 'STAFF_INVITATION'"));
        self::assertSame(['Invité', 'invite'], $this->memberStatus('hugo.martin@example.com'));

        // « Renvoyer »: a second link, the first one spent.
        $this->post('/settings/team/members/' . self::id($answer) . '/invitation', []);
        self::assertResponseIsSuccessful();
        self::assertSame(['1', '0'], $this->column("SELECT IF(used_at IS NULL, '0', '1') FROM identity_access__account_tokens ORDER BY created_at, id"));
        self::assertCount(2, $this->column("SELECT id FROM notification__outbound_messages WHERE purpose = 'STAFF_INVITATION'"));

        // 73 hours later the link is expired.
        $this->connection()->executeStatement('UPDATE identity_access__account_tokens SET expires_at = NOW() - INTERVAL 1 HOUR');
        self::assertSame(['Invitation expirée', 'expire'], $this->memberStatus('hugo.martin@example.com'));
    }

    public function testAnExistingKivetoUserJoinsWithoutEmail(): void
    {
        ClinicUserFactory::new()->withEmail('lea.ailleurs@example.com')->create();

        $answer = $this->invite('lea.ailleurs@example.com');

        self::assertSame(200, $answer['status']);
        self::assertFalse($answer['body']['invited'] ?? null);
        self::assertSame([], $this->column("SELECT id FROM notification__outbound_messages WHERE purpose = 'STAFF_INVITATION'"));
        self::assertSame([], $this->column('SELECT id FROM identity_access__account_tokens'));
    }

    public function testOtherAccountsAreRefused(): void
    {
        PortalUserFactory::new()->withEmail('proprietaire@example.com')->create();
        ClinicUserFactory::new()->withEmail('parti@example.com')->disabled()->create();

        self::assertSame([422, 'Cette adresse est déjà utilisée par un autre compte Kiveto.'], $this->refusal($this->invite('proprietaire@example.com')));
        self::assertSame([422, 'Ce compte est désactivé. Contactez le support Kiveto.'], $this->refusal($this->invite('parti@example.com')));
        self::assertSame([], $this->column("SELECT id FROM notification__outbound_messages WHERE purpose = 'STAFF_INVITATION'"));
    }

    public function testAMemberWhoActivatedCannotBeInvitedAgain(): void
    {
        $answer = $this->invite('hugo.martin@example.com');
        $this->connection()->executeStatement("UPDATE identity_access__users SET status = 'ACTIVE' WHERE email = 'hugo.martin@example.com'");

        $this->post('/settings/team/members/' . self::id($answer) . '/invitation', []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'Ce membre a déjà activé son compte.'], json_decode((string) $this->browser->getResponse()->getContent(), true));
    }

    /**
     * A3: disabling the access spends the clinic's link; « Renvoyer » is then
     * refused, as for an ended access, until the access is reopened.
     */
    public function testADisabledOrEndedAccessHasNoLiveLinkAndCannotBeSentOne(): void
    {
        $id = self::id($this->invite('hugo.martin@example.com'));
        self::assertSame(['0'], $this->column("SELECT IF(used_at IS NULL, '0', '1') FROM identity_access__account_tokens"));

        $this->post('/settings/team/members/' . $id . '/disable', []);
        self::assertResponseIsSuccessful();
        self::assertSame(['1'], $this->column("SELECT IF(used_at IS NULL, '0', '1') FROM identity_access__account_tokens"), 'the link no longer works');

        $this->post('/settings/team/members/' . $id . '/invitation', []);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'L’accès de ce membre est désactivé ou terminé : rouvrez-le avant de renvoyer l’invitation.'], $this->json());

        $this->post('/settings/team/members/' . $id . '/enable', []);
        self::assertResponseIsSuccessful();
        $this->connection()->executeStatement('UPDATE clinic__clinic_memberships SET valid_until_utc = NOW() - INTERVAL 1 DAY WHERE user_id = (SELECT id FROM identity_access__users WHERE email = ?)', ['hugo.martin@example.com']);
        $this->post('/settings/team/members/' . $id . '/invitation', []);
        self::assertResponseStatusCodeSame(422);

        self::assertCount(1, $this->column('SELECT id FROM identity_access__account_tokens'), 'no new link was issued');
    }

    public function testAMemberOfAnotherClinicIsNotFound(): void
    {
        $other      = ClinicEntityFactory::new()->withName('Clinique Voisine')->create();
        $stranger   = ClinicUserFactory::new()->withEmail('voisin@example.com')->create();
        $membership = ClinicMembershipEntityFactory::new()->withClinicId($other->getId()->toRfc4122())->withUserId($stranger->getId()->toRfc4122())->asReceptionist()->asEmployee()->create();

        $this->post('/settings/team/members/' . $membership->getId()->toRfc4122() . '/invitation', []);

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'Ce membre ne fait pas partie de cette clinique.'], $this->json());
    }

    /**
     * A5: the membership is committed before the e-mail; when the e-mail
     * cannot be prepared the admin is told to use « Renvoyer l'invitation ».
     */
    public function testAnInvitationThatCannotBeSentSaysHowToResendIt(): void
    {
        self::getContainer()->set(NotificationRequesterInterface::class, new class implements NotificationRequesterInterface {
            public function requestStaffInvitation(StaffInvitationRequest $request): void
            {
                throw new \RuntimeException('Notification is down.');
            }

            public function requestPasswordReset(PasswordResetRequest $request): void
            {
            }
        });

        $answer = $this->invite('hugo.martin@example.com');

        self::assertSame([422, 'Le membre a été ajouté, mais l’e-mail d’invitation n’a pas pu partir. Ouvrez sa fiche et utilisez « Renvoyer l’invitation ».'], $this->refusal($answer));
        self::assertSame(['Invitation expirée', 'expire'], $this->memberStatus('hugo.martin@example.com'), 'the member is there, without a live link');
        self::assertSame([], $this->column('SELECT id FROM identity_access__account_tokens'));
    }

    /**
     * A5: two invitations of the same address create one account.
     */
    public function testTheSameAddressInvitedTwiceKeepsOneAccount(): void
    {
        $first = $this->invite('hugo.martin@example.com');
        self::assertSame(200, $first['status']);

        self::assertSame(422, $this->invite('Hugo.Martin@example.com')['status'], 'already a member of this clinic');
        self::assertCount(1, $this->column("SELECT id FROM identity_access__users WHERE email = 'hugo.martin@example.com'"));
    }

    /**
     * @return array<mixed>
     */
    private function json(): array
    {
        $body = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($body));

        return $body;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function invite(string $email): array
    {
        $this->post('/settings/team/members', [
            'firstName'  => 'Hugo',
            'lastName'   => 'Martin',
            'email'      => $email,
            'role'       => 'VETERINARY_ASSISTANT',
            'engagement' => 'EMPLOYEE',
        ]);
        $body = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($body));
        $fields = [];
        foreach ($body as $key => $value) {
            $fields[(string) $key] = $value;
        }

        return ['status' => $this->browser->getResponse()->getStatusCode(), 'body' => $fields];
    }

    /**
     * @param array{status: int, body: array<string, mixed>} $answer
     */
    private static function id(array $answer): string
    {
        $id = $answer['body']['id'] ?? null;
        self::assertIsString($id);

        return $id;
    }

    /**
     * @param array{status: int, body: array<string, mixed>} $answer
     *
     * @return array{0: int, 1: mixed}
     */
    private function refusal(array $answer): array
    {
        return [$answer['status'], $answer['body']['error'] ?? null];
    }

    /**
     * @param array<string, string> $fields
     */
    private function post(string $path, array $fields): void
    {
        $this->browser->request('POST', self::HOST . $path, ['_token' => $this->token] + $fields, server: ['HTTP_ACCEPT' => 'application/json']);
    }

    /**
     * The status label and state of a member on the team page.
     *
     * @return array{0: mixed, 1: mixed}
     */
    private function memberStatus(string $email): array
    {
        $this->browser->request('GET', self::HOST . '/settings/team/data', server: ['HTTP_ACCEPT' => 'application/json']);
        $data = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($data) && \is_array($data['members'] ?? null));

        foreach ($data['members'] as $member) {
            if (\is_array($member) && $email === ($member['mail'] ?? null)) {
                return [$member['sl'] ?? null, $member['etat'] ?? null];
            }
        }

        self::fail('The member is not on the team page.');
    }

    /**
     * @return list<mixed>
     */
    private function column(string $sql): array
    {
        return $this->connection()->fetchFirstColumn($sql);
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
