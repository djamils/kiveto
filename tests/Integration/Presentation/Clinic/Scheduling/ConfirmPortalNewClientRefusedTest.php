<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic\Scheduling;

use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Domain\Time\ClockInterface;
use App\Tests\Shared\Time\FrozenClock;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * AC 266: a new client's pending request (portal, no owner yet) that no
 * pending booking request holds is never confirmed from the agenda nor the
 * dashboard: staff are sent to « Demandes Portal »; nothing changes. (With
 * its booking request, the agenda confirms it through ClientPortal, S7-01:
 * see BookingRequestPipelineTest.).
 */
final class ConfirmPortalNewClientRefusedTest extends WebTestCase
{
    use Factories;

    private const string CLINIC  = '01960000-0000-7000-8000-0000000b4c01';
    private const string PENDING = '01960000-0000-7000-8000-0000000b4a01';
    private const string HOST    = 'http://clinic.kiveto.local';

    private KernelBrowser $browser;
    private string $token = '';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        $now = new \DateTimeImmutable('today 10:00', new \DateTimeZone('Europe/Paris'));
        self::getContainer()->set(ClockInterface::class, new FrozenClock($now));

        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique des Tilleuls')->create(['timeZone' => 'Europe/Paris']);
        $user  = ClinicUserFactory::new()->withEmail('newclient.vet@example.com')->withPlainPassword('mot-de-passe')->create();
        $vetId = $user->getId()->toRfc4122();
        ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($vetId)->asVeterinary()->asEmployee()->create();

        AppointmentEntityFactory::new()->withId(self::PENDING)->withClinicId(self::CLINIC)->withPractitionerUserId($vetId)
            ->withOwnerAndAnimal(null, null)
            ->startingAt($now->modify('+1 day 14:00')->setTimezone(new \DateTimeZone('UTC')))
            ->pending($now->modify('+1 day 08:00')->setTimezone(new \DateTimeZone('UTC')))
            ->create()
        ;

        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"email":"newclient.vet@example.com","password":"mot-de-passe"}');
        self::assertResponseIsSuccessful();
        $this->browser->request('GET', self::HOST . '/scheduling/agenda');
        self::assertResponseIsSuccessful();
        self::assertSame(1, preg_match('/"token":"([^"]+)"/', (string) $this->browser->getResponse()->getContent(), $match));
        $this->token = $match[1];
    }

    public function testTheAgendaRefusesAndPointsToDemandesPortal(): void
    {
        $this->browser->request('POST', self::HOST . '/scheduling/appointments/' . self::PENDING . '/confirm', ['_token' => $this->token], server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        self::assertResponseStatusCodeSame(409);
        $answer = json_decode((string) $this->browser->getResponse()->getContent(), true);
        self::assertIsArray($answer);
        self::assertSame(['À traiter dans Demandes Portal.', 'NEW_CLIENT_REQUEST', '/portal-requests?type=booking_request'], [$answer['message'] ?? null, $answer['errorCode'] ?? null, $answer['redirect'] ?? null]);
        self::assertSame('PENDING_CLINIC_CONFIRMATION', $this->appointmentStatus());
    }

    public function testTheDashboardFormIsSentToDemandesPortalWithAFlash(): void
    {
        $this->browser->request('POST', self::HOST . '/scheduling/appointments/' . self::PENDING . '/confirm', ['_token' => $this->token]);

        self::assertResponseRedirects('/portal-requests?type=booking_request');
        $this->browser->followRedirect();
        self::assertStringContainsString('À traiter dans Demandes Portal.', (string) $this->browser->getResponse()->getContent());
        self::assertSame('PENDING_CLINIC_CONFIRMATION', $this->appointmentStatus());
    }

    private function appointmentStatus(): string
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        $status = $connection->fetchOne('SELECT status FROM scheduling__appointments WHERE id = ?', [Uuid::fromString(self::PENDING)->toBinary()]);
        \assert(\is_string($status));

        return $status;
    }
}
