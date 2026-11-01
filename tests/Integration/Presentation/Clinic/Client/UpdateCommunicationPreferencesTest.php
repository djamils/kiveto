<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic\Client;

use App\Context\Client\Application\Command\SetClientConsent\SetClientConsent;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Client\Factory\ContactMethodEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * The « Préférences » card of the client sheet (AC 55, AC 202): the history
 * rows carry the `clinic` source and the staff member signed in, and a form
 * rendered before another change of the client is refused with a flash.
 */
final class UpdateCommunicationPreferencesTest extends WebTestCase
{
    use Factories;

    private const string CLINIC = '01960000-0000-7000-8000-0000000c0dc1';
    private const string CLIENT = '01960000-0000-7000-8000-0000000c0dd1';
    private const string HOST   = 'http://clinic.kiveto.local';

    private KernelBrowser $browser;
    private string $userId = '';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique Montmartre')->create();
        $user         = ClinicUserFactory::new()->withEmail('julie.accueil@example.com')->withPlainPassword('mot-de-passe')->create();
        $this->userId = $user->getId()->toRfc4122();
        ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($this->userId)->asReceptionist()->asEmployee()->create();

        ClientEntityFactory::new()->withId(self::CLIENT)->withClinicId(self::CLINIC)->withName('Hélène', 'Lambert')->since('-2 years')->with(['status' => ClientStatus::ACTIVE])->create();
        ContactMethodEntityFactory::new()->forClient(self::CLIENT)->phone('+33611223344')->with(['label' => ContactLabel::MOBILE])->primary()->create();

        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"email":"julie.accueil@example.com","password":"mot-de-passe"}');
        self::assertResponseIsSuccessful();
    }

    /** AC 55 */
    public function testTheHistoryNamesTheClinicAndTheStaffMember(): void
    {
        [$token, $version] = $this->openSheet();

        $this->save($token, $version, ['appointment_reminders' => ['sms'], 'marketing' => ['sms']]);

        self::assertResponseRedirects('/clients/' . self::CLIENT . '?saved=cPref', 303);
        self::assertSame(['success' => ['Préférences enregistrées.']], $this->flashes());
        self::assertSame([
            ['appointment_reminders', 'sms', 'granted', 'clinic', 'staff', $this->userId, 'julie.accueil@example.com'],
            ['marketing', 'sms', 'granted', 'clinic', 'staff', $this->userId, 'julie.accueil@example.com'],
        ], $this->history());
    }

    /** AC 202 */
    public function testAFormRenderedBeforeAPortalWithdrawalIsRefused(): void
    {
        [$token, $version] = $this->openSheet();
        $this->save($token, $version, ['marketing' => ['sms']]);

        [$token, $version] = $this->openSheet();
        $bus               = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);
        $bus->dispatch(new SetClientConsent(self::CLINIC, self::CLIENT, 'marketing', [], 'portal', 'portal_user', Uuid::v7()->toRfc4122(), 'Hélène Lambert'));

        $this->save($token, $version, ['marketing' => ['sms']]);

        self::assertResponseRedirects('/clients/' . self::CLIENT, 303);
        self::assertSame(['error' => ['Les consentements ont changé depuis l\'ouverture de la fiche']], $this->flashes());
        $marketing = array_values(array_filter($this->history(), static fn (array $row): bool => 'marketing' === $row[0]));
        self::assertSame(['withdrawn', 'portal'], [$marketing[\count($marketing) - 1][2], $marketing[\count($marketing) - 1][3]], 'marketing stays withdrawn');
        self::assertCount(2, $marketing);
    }

    public function testARefusedSaveSaysSo(): void
    {
        [$token, $version] = $this->openSheet();

        $this->save($token, $version, ['marketing' => ['phone']]);

        self::assertSame(['error' => ['Les préférences de communication n’ont pas pu être enregistrées.']], $this->flashes());
        self::assertSame([], $this->history());
    }

    /**
     * @return array{string, string}
     */
    private function openSheet(): array
    {
        $crawler = $this->browser->request('GET', self::HOST . '/clients/' . self::CLIENT);
        self::assertResponseIsSuccessful();

        return [
            (string) $crawler->filter('#prefForm input[name="_token"]')->attr('value'),
            (string) $crawler->filter('#prefForm input[name="version"]')->attr('value'),
        ];
    }

    /**
     * @param array<string, list<string>> $consents
     */
    private function save(string $token, string $version, array $consents): void
    {
        $this->browser->request('POST', self::HOST . '/clients/' . self::CLIENT . '/communication-preferences', [
            '_token'            => $token,
            'version'           => $version,
            'preferred_channel' => 'sms',
            'consent'           => $consents,
        ]);
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
     * @return list<array{string, string, string, string, string, ?string, string}> oldest first
     */
    private function history(): array
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);

        $rows = $connection->fetchAllAssociative(
            'SELECT kind, channel, action, source, actor_type, BIN_TO_UUID(actor_id) AS actor_id, author_label FROM client__consent_history WHERE client_id = ? ORDER BY at, id',
            [Uuid::fromString(self::CLIENT)->toBinary()],
        );

        return array_map(static fn (array $row): array => [
            RowAccessor::string($row, 'kind'),
            RowAccessor::string($row, 'channel'),
            RowAccessor::string($row, 'action'),
            RowAccessor::string($row, 'source'),
            RowAccessor::string($row, 'actor_type'),
            RowAccessor::nullableString($row, 'actor_id'),
            RowAccessor::string($row, 'author_label'),
        ], $rows);
    }
}
