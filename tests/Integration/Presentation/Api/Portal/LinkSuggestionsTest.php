<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\ClientPortal\Application\Command\ConfirmClientLink\ConfirmClientLink;
use App\Context\ClientPortal\Application\Command\RejectClientLink\RejectClientLink;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Embeddable\LetterheadEmbeddable;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Client\Factory\ContactMethodEntityFactory;
use App\Fixtures\Context\ClientPortal\Factory\ClientLinkEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\PostInAppNotification\PostInAppNotification;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * S7b link suggestions through the Portal API (AC 75, 76, 88): one
 * suggestion per clinic holding a file with the account's mobile, accepted
 * into pending links the clinic confirms or rejects.
 */
final class LinkSuggestionsTest extends PortalApiTestCase
{
    private const string PHONE     = '+33612340455';
    private const string CLINIC_A  = '01970000-0000-7000-8000-00000045a001';
    private const string CLINIC_B  = '01970000-0000-7000-8000-00000045a002';
    private const string SUSPENDED = '01970000-0000-7000-8000-00000045a003';
    private const string CLOSED    = '01970000-0000-7000-8000-00000045a004';
    private const string LINKED    = '01970000-0000-7000-8000-00000045a005';

    private string $token = '';

    private string $userId = '';

    /** @var array<string, string> */
    private array $files = [];

    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.45';
        parent::setUp();

        ClinicEntityFactory::new()->withId(self::CLINIC_A)->withName('Clinique des Tilleuls')->withLetterhead(new LetterheadEmbeddable(streetLine1: '4 place Bellecour', postalCode: '69002', city: 'Lyon', countryCode: 'FR'))->create();
        ClinicEntityFactory::new()->withId(self::CLINIC_B)->withName('Clinique du Port')->create();
        ClinicEntityFactory::new()->withId(self::SUSPENDED)->withName('Clinique suspendue')->suspended()->create();
        ClinicEntityFactory::new()->withId(self::CLOSED)->withName('Clinique fermée')->closed()->create();
        ClinicEntityFactory::new()->withId(self::LINKED)->withName('Clinique déjà reliée')->create();

        $this->files = [
            'sophie' => $this->file(self::CLINIC_A, 'Sophie', 'Martin', consents: true),
            'paul'   => $this->file(self::CLINIC_A, 'Paul', 'Martin'),
            'port'   => $this->file(self::CLINIC_B, 'Sophie', 'Martin'),
            'home'   => $this->file(self::CLINIC_B, 'Fixe', 'Martin', ContactLabel::HOME),
            'old'    => $this->file(self::CLINIC_B, 'Archivée', 'Martin', archived: true),
            'susp'   => $this->file(self::SUSPENDED, 'Sophie', 'Martin'),
            'closed' => $this->file(self::CLOSED, 'Sophie', 'Martin'),
            'linked' => $this->file(self::LINKED, 'Sophie', 'Martin'),
        ];

        $assistant = ClinicUserFactory::new()->withEmail('link.asv@example.com')->create();
        ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC_A)->withUserId($assistant->getId()->toRfc4122())->asVeterinary()->asEmployee()->create();

        $user         = PortalUserFactory::new()->withPhone(self::PHONE)->create();
        $this->userId = $user->getId()->toRfc4122();
        ClientLinkEntityFactory::new()->between($this->userId, self::LINKED, $this->files['linked'])->create();
        $this->token = self::string($this->signIn(self::PHONE)['accessToken']);
    }

    /** AC 75. */
    public function testOneSuggestionPerClinicWithItsNameAndCityOnly(): void
    {
        $suggestions = $this->suggestions();

        self::assertSame([
            ['clinic' => ['name' => 'Clinique des Tilleuls', 'city' => 'Lyon'], 'matchedOn' => 'phone'],
            ['clinic' => ['name' => 'Clinique du Port', 'city' => null], 'matchedOn' => 'phone'],
        ], array_map(static fn (array $s): array => array_diff_key($s, ['id' => true]), $suggestions));
        foreach ($suggestions as $suggestion) {
            self::assertSame(['id', 'clinic', 'matchedOn'], array_keys($suggestion));
            self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', self::string($suggestion['id']));
        }
        self::assertSame(401, $this->get('/me/link-suggestions')->getStatusCode());
    }

    /** AC 76: pending, then confirmed by the clinic. */
    public function testAnAcceptedSuggestionWaitsForTheClinicThenOpensIt(): void
    {
        $id = self::string($this->suggestions()[0]['id']);

        self::assertSame(['linkStatus' => 'pending'], self::json($this->authPost('/me/link-suggestions/' . $id . '/accept'), 202));
        self::assertSame(['linkStatus' => 'pending'], self::json($this->authPost('/me/link-suggestions/' . $id . '/accept'), 202), 'replay');

        self::assertSame([['name' => 'Clinique du Port', 'city' => null]], array_column($this->suggestions(), 'clinic'));
        self::assertSame([self::CLINIC_A, 'pending', null, [], 0], $this->meClinic(self::CLINIC_A));
        self::assertSame('not_found', self::problemType($this->authGet('/clinics/' . self::CLINIC_A . '/pets'), 404));

        // Both family files wait for the clinic, which is told once.
        self::assertSame(2, $this->number('SELECT COUNT(*) FROM client_portal__client_links WHERE user_id = UUID_TO_BIN(?) AND clinic_id = UUID_TO_BIN(?) AND status = ?', [$this->userId, self::CLINIC_A, 'pending']));
        $bells = array_values(array_filter($this->bus()->recorded(PostInAppNotification::class), static fn (PostInAppNotification $n): bool => 'clinic_portal_link_requests_show' === $n->route));
        self::assertCount(1, $bells);

        $this->clinicAnswers(new ConfirmClientLink(self::CLINIC_A, $this->userId, [$this->files['sophie'], $this->files['paul']], '01960000-0000-7000-8000-00000000e001', 'Dr Vet'));

        $clinic = $this->meClinic(self::CLINIC_A);
        self::assertNotNull($clinic);
        self::assertSame([self::CLINIC_A, 'confirmed'], [$clinic[0], $clinic[1]]);
        self::assertIsString($clinic[2]);
        self::assertSame(200, $this->authGet('/clinics/' . self::CLINIC_A . '/pets')->getStatusCode());
    }

    /** AC 76, 88: rejected links are revoked, consents untouched, the suggestion never comes back. */
    public function testARejectedSuggestionNeverComesBack(): void
    {
        $consents = $this->consentState($this->files['sophie']);
        $id       = self::string($this->suggestions()[0]['id']);
        self::json($this->authPost('/me/link-suggestions/' . $id . '/accept'), 202);

        $this->clinicAnswers(new RejectClientLink(self::CLINIC_A, $this->userId, [$this->files['sophie'], $this->files['paul']], '01960000-0000-7000-8000-00000000e001', 'Dr Vet'));

        self::assertSame(['Clinique du Port'], array_column(array_column($this->suggestions(), 'clinic'), 'name'));
        self::assertNull($this->meClinic(self::CLINIC_A));
        self::assertSame('not_found', self::problemType($this->authPost('/me/link-suggestions/' . $id . '/accept'), 404));
        self::assertSame(2, $this->number('SELECT COUNT(*) FROM client_portal__client_links WHERE user_id = UUID_TO_BIN(?) AND clinic_id = UUID_TO_BIN(?) AND status = ?', [$this->userId, self::CLINIC_A, 'revoked']));
        self::assertSame($consents, $this->consentState($this->files['sophie']));
    }

    public function testAnUnknownSuggestionIsNotFound(): void
    {
        self::assertSame('not_found', self::problemType($this->authPost('/me/link-suggestions/' . str_repeat('0', 64) . '/accept'), 404));
        self::assertSame(401, $this->post('/me/link-suggestions/' . str_repeat('0', 64) . '/accept', [])->getStatusCode());
    }

    private function file(string $clinicId, string $firstName, string $lastName, ContactLabel $label = ContactLabel::MOBILE, bool $archived = false, bool $consents = false): string
    {
        $id      = Uuid::v7()->toRfc4122();
        $factory = ClientEntityFactory::new()->withId($id)->withClinicId($clinicId)->withName($firstName, $lastName);
        $factory = $archived ? $factory->archived() : $factory->active();

        if ($consents) {
            $factory = $factory->withCommunicationPreferences('sms', ['marketing' => ['sms', 'email'], 'appointment_reminders' => ['sms']], '2026-10-01T09:00:00+02:00');
        }

        $factory->create();
        ContactMethodEntityFactory::new()->forClient($id)->phone(self::PHONE)->create(['label' => $label, 'isPrimary' => true]);

        return $id;
    }

    private function clinicAnswers(object $command): void
    {
        $bus = $this->browser->getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);
        $bus->dispatch($command);
    }

    /**
     * @return array{0: string, 1: string, 2: ?string, 3: mixed, 4: mixed}|null
     */
    private function meClinic(string $clinicId): ?array
    {
        $clinics = self::json($this->authGet('/me'), 200)['clinics'] ?? null;
        self::assertIsArray($clinics);

        foreach ($clinics as $clinic) {
            self::assertIsArray($clinic);

            if ($clinicId === ($clinic['clinicId'] ?? null)) {
                self::assertArrayHasKey('linkedAt', $clinic);
                $linkedAt = $clinic['linkedAt'];
                self::assertTrue(null === $linkedAt || \is_string($linkedAt));

                return [$clinicId, self::string($clinic['linkStatus'] ?? null), $linkedAt, $clinic['petNames'] ?? null, $clinic['unreadDocuments'] ?? null];
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function suggestions(): array
    {
        $response = $this->authGet('/me/link-suggestions');
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $list = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($list);

        return array_values(array_map(static function (mixed $item): array {
            self::assertIsArray($item);

            $fields = [];
            foreach ($item as $key => $value) {
                $fields[(string) $key] = $value;
            }

            return $fields;
        }, $list));
    }

    /**
     * @return array{0: mixed, 1: int}
     */
    private function consentState(string $clientId): array
    {
        return [
            $this->connection()->fetchOne('SELECT communication_preferences FROM client__clients WHERE id = UUID_TO_BIN(?)', [$clientId]),
            $this->number('SELECT COUNT(*) FROM client__consent_history WHERE client_id = UUID_TO_BIN(?)', [$clientId]),
        ];
    }

    private function authGet(string $path): Response
    {
        return $this->get($path, ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token]);
    }

    private function authPost(string $path): Response
    {
        return $this->post($path, [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token]);
    }
}
