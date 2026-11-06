<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Client;

use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Embeddable\LetterheadEmbeddable;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Client\Factory\ContactMethodEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Clinic\Factory\StaffProfileEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use App\Tests\Support\RecordingCommandBus;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * « Inviter au Portal » on the client sheet (Task 17 — AC 20 to 24, 31, 73):
 * the action posts SendPortalInvitation, refuses the clients the Portal
 * cannot reach, resends under the same invitation id, and the sheet names
 * the action of each Portal state.
 */
final class PortalInvitationActionTest extends WebTestCase
{
    use Factories;

    private const string CLINIC = '01960000-0000-7000-8000-0000000f17c1';
    private const string HOST   = 'http://clinic.kiveto.local';

    private const string INVITABLE = '01960000-0000-7000-8000-0000000f17d1';
    private const string NO_MOBILE = '01960000-0000-7000-8000-0000000f17d2';
    private const string FOREIGN   = '01960000-0000-7000-8000-0000000f17d3';
    private const string NO_EMAIL  = '01960000-0000-7000-8000-0000000f17d4';
    private const string ARCHIVED  = '01960000-0000-7000-8000-0000000f17d5';

    private KernelBrowser $browser;
    private string $token = '';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique Montmartre')
            ->withLetterhead(new LetterheadEmbeddable(streetLine1: '18 rue des Abbesses', postalCode: '75018', city: 'Paris', countryCode: 'FR'))
            ->create()
        ;
        $user       = ClinicUserFactory::new()->withEmail('julie.accueil@example.com')->withPlainPassword('mot-de-passe')->create();
        $membership = ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($user->getId()->toRfc4122())->asReceptionist()->asEmployee()->create();
        StaffProfileEntityFactory::new()->forMembership($membership->getId())->with(['firstName' => 'Julie', 'lastName' => 'Martin'])->create();

        $this->client(self::INVITABLE, 'Sophie', 'Garnier', ['+33648217730', 'mobile'], 'sophie.garnier@example.com');
        $this->client(self::NO_MOBILE, 'Bernard', 'Giraud', ['+33145678910', 'home'], 'bernard.giraud@example.com');
        $this->client(self::FOREIGN, 'Lukas', 'Weber', ['+4915112345678', 'mobile'], 'lukas.weber@example.com');
        $this->client(self::NO_EMAIL, 'Chloé', 'Lambert', ['+33655667788', 'mobile'], null);
        $this->client(self::ARCHIVED, 'Élise', 'Moreau', ['+33644556677', 'mobile'], null, archived: true);

        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"email":"julie.accueil@example.com","password":"mot-de-passe"}');
        self::assertResponseIsSuccessful();

        $crawler = $this->browser->request('GET', self::HOST . '/clients/' . self::INVITABLE);
        self::assertResponseIsSuccessful();
        $this->token = (string) $crawler->filter('#coForm input[name="_token"]')->attr('value');

        $this->recorder()->reset();
    }

    /** AC 20: an SMS invitation is persisted pending for 7 days and its link carries the token in the fragment. */
    public function testAClientWithAMobileIsInvitedBySms(): void
    {
        self::assertSame(['not_invited', 'Portal : non invité', 'Inviter au Portal', true], $this->portal(self::INVITABLE, ['state', 'badge', 'action', 'invitable']));
        $this->recorder()->reset();

        $this->invite(self::INVITABLE, 'sms');

        self::assertResponseRedirects('/clients/' . self::INVITABLE . '?saved=head', 303);
        self::assertSame(['success' => ['Invitation envoyée']], $this->flashes());

        $rows = $this->invitations(self::INVITABLE);
        self::assertCount(1, $rows);
        self::assertSame(['pending', 'sms', '+33648217730'], [$rows[0]['status'], $rows[0]['channel'], $rows[0]['phone']]);
        self::assertSame(7 * 86400, strtotime($rows[0]['expires_at']) - strtotime($rows[0]['sent_at']));

        $messages = $this->portalMessages();
        self::assertCount(1, $messages);
        self::assertSame(['SMS', '+33648217730', self::INVITABLE, self::CLINIC], [$messages[0]->channel, $messages[0]->recipientAddress, $messages[0]->clientId, $messages[0]->clinicId]);
        self::assertIsString($messages[0]->parameters['link'] ?? null);
        self::assertStringContainsString('/invitation#t=', $messages[0]->parameters['link']);

        $portal = $this->portal(self::INVITABLE, ['state', 'action', 'resend']);
        self::assertSame(['invited', 'Renvoyer l’invitation', true], $portal);
        self::assertStringStartsWith('Portal : invité le ', $this->badge(self::INVITABLE));
    }

    /** AC 23: « Renvoyer l'invitation » keeps the invitation id, replaces the token and sends a new message. */
    public function testResendingKeepsTheInvitationAndReplacesItsToken(): void
    {
        $this->invite(self::INVITABLE, 'sms');
        $first = $this->invitations(self::INVITABLE)[0];
        $this->connection()->executeStatement('UPDATE client_portal__portal_invitations SET sent_at = sent_at - INTERVAL 1 DAY, expires_at = expires_at - INTERVAL 1 DAY');
        // Back on the sheet, which shows the first flash.
        self::assertSame(['invited'], $this->portal(self::INVITABLE, ['state']));

        $this->invite(self::INVITABLE, 'email');

        self::assertSame(['success' => ['Invitation renvoyée']], $this->flashes());
        $rows = $this->invitations(self::INVITABLE);
        self::assertCount(1, $rows);
        self::assertSame([$first['id'], 'pending', 'email'], [$rows[0]['id'], $rows[0]['status'], $rows[0]['channel']]);
        self::assertNotSame($first['token_hash'], $rows[0]['token_hash']);
        self::assertGreaterThan(strtotime($first['expires_at']) - 86400, strtotime($rows[0]['expires_at']));

        $messages = $this->portalMessages();
        self::assertCount(2, $messages);
        self::assertSame(['EMAIL', 'sophie.garnier@example.com'], [$messages[1]->channel, $messages[1]->recipientAddress]);
        self::assertNotSame($messages[0]->parameters['link'] ?? null, $messages[1]->parameters['link'] ?? null);
    }

    /** AC 24: an invitation past its 7 days shows « Invitation expirée » and offers « Renvoyer l'invitation ». */
    public function testAnExpiredInvitationOffersToResendIt(): void
    {
        $this->invite(self::INVITABLE, 'sms');
        $this->connection()->executeStatement('UPDATE client_portal__portal_invitations SET sent_at = sent_at - INTERVAL 8 DAY, expires_at = expires_at - INTERVAL 8 DAY');

        self::assertSame(['expired', 'Portal : invitation expirée', 'Renvoyer l’invitation'], $this->portal(self::INVITABLE, ['state', 'badge', 'action']));
    }

    /** AC 21: no SMS-capable phone — the action is disabled, a forced POST is refused and nothing is sent. */
    public function testAClientWithoutMobileIsNotInvited(): void
    {
        self::assertSame([false, 'no_mobile', 'Ajoutez un numéro de mobile pour inviter ce client'], $this->portal(self::NO_MOBILE, ['invitable', 'notInvitableReason', 'notInvitableText']));
        $this->recorder()->reset();

        $this->invite(self::NO_MOBILE, 'sms');

        self::assertResponseRedirects('/clients/' . self::NO_MOBILE, 303);
        self::assertSame(['error' => ['Ajoutez un numéro de mobile pour inviter ce client']], $this->flashes());
        self::assertSame([], $this->invitations(self::NO_MOBILE));
        self::assertSame([], $this->portalMessages());
    }

    /** AC 73: a foreign number labelled « mobile » reaches SMS but not the Portal. */
    public function testAForeignMobileIsNotSupported(): void
    {
        self::assertSame([false, 'mobile_not_supported', 'Ce numéro de mobile n’est pas encore pris en charge par le Portal'], $this->portal(self::FOREIGN, ['invitable', 'notInvitableReason', 'notInvitableText']));
        $this->recorder()->reset();

        $this->invite(self::FOREIGN, 'sms');

        self::assertSame(['error' => ['Ce numéro de mobile n’est pas encore pris en charge par le Portal']], $this->flashes());
        self::assertSame([], $this->invitations(self::FOREIGN));
        self::assertSame([], $this->portalMessages());
    }

    /** AC 22: without e-mail the E-mail segment is disabled and an e-mail invitation is refused. */
    public function testAClientWithoutEmailCannotBeInvitedByEmail(): void
    {
        self::assertSame([true, false, '06 •• •• •• 88', null], $this->portal(self::NO_EMAIL, ['invitable', 'hasEmail', 'mobileMasked', 'emailMasked']));
        self::assertStringContainsString('data-v="email" disabled', (string) $this->browser->getResponse()->getContent());

        $this->invite(self::NO_EMAIL, 'email');

        self::assertSame(['error' => ['Ajoutez une adresse e-mail pour inviter ce client par e-mail']], $this->flashes());
        self::assertSame([], $this->invitations(self::NO_EMAIL));
    }

    public function testAnArchivedClientIsNotInvited(): void
    {
        self::assertSame([false, 'archived'], $this->portal(self::ARCHIVED, ['invitable', 'notInvitableReason']));

        $this->invite(self::ARCHIVED, 'sms');

        self::assertSame(['error' => ['Ce client est archivé']], $this->flashes());
        self::assertSame([], $this->invitations(self::ARCHIVED));
    }

    /** AC 31: a withdrawn access shows « Accès retiré » and offers « Réinviter », which sends a new invitation. */
    public function testAWithdrawnAccessOffersToInviteAgain(): void
    {
        $this->invite(self::INVITABLE, 'sms');
        $first = $this->invitations(self::INVITABLE)[0]['id'];
        $this->connection()->executeStatement("UPDATE client_portal__portal_invitations SET status = 'revoked', revoked_at = NOW(), sent_at = NOW() - INTERVAL 1 MONTH");
        $this->connection()->insert('client_portal__client_links', [
            'id'         => Uuid::v7()->toBinary(),
            'user_id'    => Uuid::v7()->toBinary(),
            'clinic_id'  => Uuid::fromString(self::CLINIC)->toBinary(),
            'client_id'  => Uuid::fromString(self::INVITABLE)->toBinary(),
            'status'     => 'revoked',
            'linked_at'  => (new \DateTimeImmutable('-20 days'))->format('Y-m-d H:i:s'),
            'revoked_at' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
        ]);

        self::assertSame(['revoked', 'Portal : accès retiré', 'Réinviter', false], $this->portal(self::INVITABLE, ['state', 'badge', 'action', 'resend']));
        $this->invite(self::INVITABLE, 'sms');

        self::assertSame(['success' => ['Invitation envoyée']], $this->flashes());
        $ids = array_column($this->invitations(self::INVITABLE), 'id');
        self::assertCount(2, $ids);
        self::assertContains($first, $ids);
        self::assertSame('invited', $this->portal(self::INVITABLE, ['state'])[0]);
    }

    public function testAnActiveClientHasNoActionAndIsNotInvitedAgain(): void
    {
        $this->connection()->insert('client_portal__client_links', [
            'id'        => Uuid::v7()->toBinary(),
            'user_id'   => Uuid::v7()->toBinary(),
            'clinic_id' => Uuid::fromString(self::CLINIC)->toBinary(),
            'client_id' => Uuid::fromString(self::INVITABLE)->toBinary(),
            'status'    => 'confirmed',
            'linked_at' => (new \DateTimeImmutable('-20 days'))->format('Y-m-d H:i:s'),
        ]);

        self::assertSame(['active', null], $this->portal(self::INVITABLE, ['state', 'action']));
        self::assertStringStartsWith('Portal : actif depuis le ', $this->badge(self::INVITABLE));
        self::assertStringNotContainsString('id="mPortal"', (string) $this->browser->getResponse()->getContent());

        $this->invite(self::INVITABLE, 'sms');

        self::assertSame(['error' => ['Ce client a déjà accès au Portal.']], $this->flashes());
        self::assertSame([], $this->invitations(self::INVITABLE));
    }

    /** AC 22: without manage_clients the action is hidden and the POST answers 403. */
    public function testAMemberWithoutManageClientsIsRefused(): void
    {
        self::assertTrue($this->portal(self::INVITABLE, ['canManage'])[0]);
        self::assertStringContainsString('id="mPortal"', (string) $this->browser->getResponse()->getContent());

        // A membership no longer in force loses the clinic at the next request
        // (RequireClinicSelectionSubscriber), before any permission is read.
        $this->connection()->executeStatement("UPDATE clinic__clinic_memberships SET engagement = 'CONTRACTOR', valid_until_utc = NOW() - INTERVAL 1 DAY");

        $this->invite(self::INVITABLE, 'sms');

        self::assertResponseRedirects('/select-clinic');
        self::assertSame([], $this->invitations(self::INVITABLE));
        self::assertSame([], $this->portalMessages());
    }

    /**
     * @param array{0: string, 1: string} $phone number and label
     */
    private function client(string $id, string $firstName, string $lastName, array $phone, ?string $email, bool $archived = false): void
    {
        $factory = ClientEntityFactory::new()->withId($id)->withClinicId(self::CLINIC)->withName($firstName, $lastName)->since('-2 years');
        ($archived ? $factory->archived() : $factory->with(['status' => ClientStatus::ACTIVE]))->create();

        $method = ContactMethodEntityFactory::new()->forClient($id)->phone($phone[0])->primary();
        ('mobile' === $phone[1] ? $method->with(['label' => ContactLabel::MOBILE]) : $method->home())->create();

        if (null !== $email) {
            ContactMethodEntityFactory::new()->forClient($id)->email($email)->home()->primary()->create();
        }
    }

    private function invite(string $clientId, string $channel): void
    {
        $this->browser->request('POST', self::HOST . '/clients/' . $clientId . '/portal-invitation', ['_token' => $this->token, 'channel' => $channel]);
    }

    /**
     * Opens the sheet and reads keys of its `portal` block.
     *
     * @param list<string> $keys
     *
     * @return list<mixed>
     */
    private function portal(string $clientId, array $keys): array
    {
        $this->browser->request('GET', self::HOST . '/clients/' . $clientId);
        self::assertResponseIsSuccessful();

        $sheet = json_decode(html_entity_decode((string) $this->browser->getCrawler()->filter('.dossier[data-sheet]')->attr('data-sheet')), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($sheet);
        self::assertIsArray($sheet['portal'] ?? null);
        $portal = $sheet['portal'];

        return array_map(static fn (string $key): mixed => $portal[$key] ?? null, $keys);
    }

    private function badge(string $clientId): string
    {
        $badge = $this->portal($clientId, ['badge'])[0];
        self::assertIsString($badge);

        return $badge;
    }

    /**
     * @return array<mixed>
     */
    private function flashes(): array
    {
        $session = $this->browser->getRequest()->getSession();
        \assert($session instanceof FlashBagAwareSessionInterface);

        return $session->getFlashBag()->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function invitations(string $clientId): array
    {
        $rows = $this->connection()->fetchAllAssociative(
            'SELECT BIN_TO_UUID(id) AS id, status, channel, phone, token_hash, sent_at, expires_at FROM client_portal__portal_invitations WHERE client_id = ? ORDER BY sent_at, id',
            [Uuid::fromString($clientId)->toBinary()],
        );

        return array_map(static fn (array $row): array => array_map(static fn (mixed $value): string => \is_string($value) ? $value : '', $row), $rows);
    }

    /**
     * @return list<RequestNotification>
     */
    private function portalMessages(): array
    {
        return array_values(array_filter(
            $this->recorder()->recorded(RequestNotification::class),
            static fn (RequestNotification $message): bool => 'PORTAL_INVITATION' === $message->purpose,
        ));
    }

    private function recorder(): RecordingCommandBus
    {
        $bus = self::getContainer()->get(RecordingCommandBus::class);
        \assert($bus instanceof RecordingCommandBus);

        return $bus;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
