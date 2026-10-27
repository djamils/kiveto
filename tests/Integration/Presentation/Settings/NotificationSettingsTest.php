<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Settings;

use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * Paramètres › Notifications: the SMS sender name (no.exp) and the overage
 * cap (no.cap) saved whole, each refusal naming its field, Notification's
 * copy kept current; reserved to who manages the staff.
 */
final class NotificationSettingsTest extends WebTestCase
{
    use Factories;

    private const string HOST   = 'http://clinic.kiveto.local';
    private const string CLINIC = '01960000-0000-7000-8000-00000000e5c1';
    private const string SENDER = 'Le nom d’expéditeur compte 11 caractères au plus : lettres sans accents, chiffres et espaces, dont au moins une lettre.';

    private KernelBrowser $browser;
    private string $token = '';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique des Lilas')->create();
    }

    public function testTheSenderAndTheCapAreSavedAndPushedToNotification(): void
    {
        $this->signIn(administrator: true);

        $this->save(['no.exp' => ' Lilas Vet ', 'no.cap' => '300']);
        self::assertResponseIsSuccessful();
        self::assertSame(['ok' => true], $this->answer());

        $settings = json_decode($this->value('SELECT messaging_settings FROM clinic__clinics WHERE id = ?'), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($settings));
        self::assertSame(['Lilas Vet', 300], [$settings['smsSender'] ?? null, $settings['overageCapSegments'] ?? null]);
        self::assertSame('Lilas Vet', $this->value('SELECT sms_sender FROM notification__clinic_messaging_profiles WHERE clinic_id = ?'));
        self::assertSame('300', $this->value('SELECT overage_cap_segments FROM notification__clinic_messaging_profiles WHERE clinic_id = ?'));
    }

    public function testEachRefusalNamesItsField(): void
    {
        $this->signIn(administrator: true);

        $cases = [
            [['no.cap' => '300'], 'Indiquez le nom d’expéditeur des SMS.', 'no.exp'],
            [['no.exp' => 'Lilas'], 'Indiquez le plafond de dépassement, en segments.', 'no.cap'],
            [['no.exp' => 'Clinique des Lilas', 'no.cap' => '300'], self::SENDER, 'no.exp'],
            [['no.exp' => '12345', 'no.cap' => '300'], self::SENDER, 'no.exp'],
            [['no.exp' => 'Lilas', 'no.cap' => 100001], 'Le plafond doit être compris entre 0 et 100 000 segments.', 'no.cap'],
        ];

        foreach ($cases as [$values, $error, $field]) {
            $this->save($values);
            self::assertResponseStatusCodeSame(422);
            self::assertSame(['error' => $error, 'field' => $field], $this->answer());
        }
    }

    public function testOnlyWhoManagesTheStaffMaySave(): void
    {
        $this->signIn(administrator: false);

        $this->save(['no.exp' => 'Lilas', 'no.cap' => '300']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'Vous n’avez pas accès à ces réglages.'], $this->answer());
    }

    private function signIn(bool $administrator): void
    {
        $user    = ClinicUserFactory::new()->withEmail('membre.lilas@example.com')->withPlainPassword('mot-de-passe')->create();
        $factory = ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($user->getId()->toRfc4122())->asEmployee();
        ($administrator ? $factory->asManager()->asClinicAdministrator() : $factory->asReceptionist())->create();

        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"email":"membre.lilas@example.com","password":"mot-de-passe"}');
        self::assertResponseIsSuccessful();

        if ($administrator) {
            $this->browser->request('GET', self::HOST . '/settings/notifications');
            self::assertResponseIsSuccessful();
            self::assertSame(1, preg_match('/&quot;token&quot;:&quot;([^&]+)&quot;/', (string) $this->browser->getResponse()->getContent(), $match));
            $this->token = $match[1];
        }
    }

    /**
     * @param array<string, int|string> $values
     */
    private function save(array $values): void
    {
        $this->browser->request('POST', self::HOST . '/settings/notifications', ['_token' => $this->token, 'values' => json_encode($values, \JSON_THROW_ON_ERROR)], server: ['HTTP_ACCEPT' => 'application/json']);
    }

    /**
     * @return array<mixed>
     */
    private function answer(): array
    {
        $answer = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($answer));

        return $answer;
    }

    private function value(string $sql): string
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $value = $connection->fetchOne($sql, [Uuid::fromString(self::CLINIC)->toBinary()]);

        return \is_scalar($value) ? (string) $value : '';
    }
}
