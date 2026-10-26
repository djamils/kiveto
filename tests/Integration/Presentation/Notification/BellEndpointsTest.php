<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Notification;

use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\PostInAppNotification\PostInAppNotification;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * AC 40: the bell of the topbar — unread dot, dropdown with « Tout » and
 * « Activité » only, a click marks read, « Tout marquer lu », the 30-day
 * history — always for the member in the current clinic only.
 */
final class BellEndpointsTest extends WebTestCase
{
    use Factories;

    private const string MONTMARTRE = '01960000-0000-7000-8000-00000000b0c1';
    private const string LILAS      = '01960000-0000-7000-8000-00000000b0c2';
    private const string HOST       = 'http://clinic.kiveto.local';

    private KernelBrowser $browser;
    private string $userId;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        ClinicEntityFactory::new()->withId(self::MONTMARTRE)->withName('Clinique Montmartre')->create(['timeZone' => 'Europe/Paris']);
        ClinicEntityFactory::new()->withId(self::LILAS)->withName('Clinique des Lilas')->create(['timeZone' => 'Europe/Paris']);
        $user         = ClinicUserFactory::new()->withEmail('rousseau@example.com')->withPlainPassword('mot-de-passe')->create();
        $this->userId = $user->getId()->toRfc4122();
        foreach ([self::MONTMARTRE, self::LILAS] as $clinicId) {
            ClinicMembershipEntityFactory::new()->withClinicId($clinicId)->withUserId($this->userId)->asVeterinary()->asEmployee()->create();
        }

        $this->post(self::MONTMARTRE, 'NEW_APPOINTMENT', 'Nouveau RDV · Milo', 'clinic_scheduling_agenda', ['view' => 'day', 'date' => '2026-10-22', 'rdv' => '01960000-0000-7000-8000-0000000a0001'], 'new-appointment:1');
        $this->post(self::MONTMARTRE, 'CRITICAL_STOCK', 'Stock critique · vermifuge', 'clinic_stock', ['article' => 'a-1'], 'stock-alert:1');
        $this->post(self::LILAS, 'EXPIRING_LOTS', 'Péremption proche · 2 lots', 'clinic_stock', ['onglet' => 'alertes'], 'expiring:lilas');

        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"email":"rousseau@example.com","password":"mot-de-passe"}');
        self::assertResponseIsSuccessful();
        $this->switchTo(self::MONTMARTRE);
    }

    public function testTheBellShowsTheCurrentClinicsEntriesOnly(): void
    {
        $page = $this->page('/notifications/history');
        // The dot is shown, the dropdown has only « Tout » and « Activité ».
        self::assertMatchesRegularExpression('/<span class="tb-dot"><\/span>/', $page);
        self::assertStringContainsString('data-f="activite">Activité</button>', $page);
        self::assertStringNotContainsString('Tâches', $page);
        self::assertStringContainsString('Nouveau RDV · Milo', $page);
        self::assertStringNotContainsString('Péremption proche', $page);

        self::assertSame(['unread' => 2], $this->json('GET', '/notifications?count=1'));

        $list = $this->json('GET', '/notifications');
        self::assertSame(2, $list['unread']);
        $html = \is_string($list['html'] ?? null) ? $list['html'] : '';
        self::assertStringContainsString('Stock critique · vermifuge', $html);
        self::assertStringContainsString('data-href="/scheduling/agenda?rdv=01960000-0000-7000-8000-0000000a0001&amp;date=2026-10-22&amp;view=day"', $html);
        self::assertStringContainsString('data-k="activite"', $html);
        self::assertStringNotContainsString('Péremption proche', $html);

        // The other clinic has its own bell.
        $this->switchTo(self::LILAS);
        self::assertSame(['unread' => 1], $this->json('GET', '/notifications?count=1'));
        self::assertStringContainsString('Péremption proche · 2 lots', $this->page('/notifications/history'));
    }

    public function testAClickMarksReadAndTheRestIsClearedAtOnce(): void
    {
        $token = $this->token();
        $first = $this->entryIds(self::MONTMARTRE)[0];

        self::assertSame(['unread' => 1], $this->json('POST', '/notifications/' . $first . '/read', ['_token' => $token]));
        self::assertSame(['unread' => 1], $this->json('POST', '/notifications/' . $first . '/read', ['_token' => $token]));

        self::assertSame(['unread' => 0], $this->json('POST', '/notifications/read-all', ['_token' => $token]));
        self::assertSame(['unread' => 0], $this->json('GET', '/notifications?count=1'));
        self::assertMatchesRegularExpression('/<span class="tb-dot" style="display:none"><\/span>/', $this->page('/notifications/history'));

        // Only the current clinic's entries were read.
        $this->switchTo(self::LILAS);
        self::assertSame(['unread' => 1], $this->json('GET', '/notifications?count=1'));
    }

    public function testAForgedRequestIsRefused(): void
    {
        $this->json('POST', '/notifications/read-all', ['_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);
        $this->json('POST', '/notifications/' . $this->entryIds(self::MONTMARTRE)[0] . '/read', ['_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);

        self::assertSame(['unread' => 2], $this->json('GET', '/notifications?count=1'));
    }

    /**
     * @param array<string, string> $params
     */
    private function post(string $clinicId, string $kind, string $title, string $route, array $params, string $key): void
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);
        $bus->dispatch(new PostInAppNotification($clinicId, [$this->userId], $kind, $title, 'détail', $route, $params, $key));
    }

    private function switchTo(string $clinicId): void
    {
        $this->browser->request('GET', self::HOST . '/select-clinic');
        self::assertSame(1, preg_match('/name="_token" value="([^"]+)"/', (string) $this->browser->getResponse()->getContent(), $match));
        $this->browser->request('POST', self::HOST . '/select-clinic', ['clinic_id' => $clinicId, '_token' => $match[1]]);
        self::assertResponseRedirects();
    }

    private function page(string $path): string
    {
        $this->browser->request('GET', self::HOST . $path);
        self::assertResponseIsSuccessful();

        return (string) $this->browser->getResponse()->getContent();
    }

    private function token(): string
    {
        self::assertSame(1, preg_match('/data-token="([^"]+)"/', $this->page('/notifications/history'), $match));

        return $match[1];
    }

    /**
     * @param array<string, string> $body
     *
     * @return array<string, mixed>
     */
    private function json(string $method, string $path, array $body = []): array
    {
        $this->browser->request($method, self::HOST . $path, $body, server: ['HTTP_ACCEPT' => 'application/json']);
        $decoded = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($decoded));
        $fields = [];
        foreach ($decoded as $key => $value) {
            $fields[(string) $key] = $value;
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function entryIds(string $clinicId): array
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return array_map(
            static fn (mixed $id): string => \is_string($id) ? Uuid::fromBinary($id)->toRfc4122() : '',
            $connection->fetchFirstColumn('SELECT id FROM notification__in_app_notifications WHERE clinic_id = ? ORDER BY created_at, id', [Uuid::fromString($clinicId)->toBinary()]),
        );
    }
}
