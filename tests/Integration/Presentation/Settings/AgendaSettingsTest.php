<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Settings;

use App\Context\Scheduling\Domain\Notification\AppointmentNotificationStateRepositoryInterface;
use App\Context\Scheduling\Domain\Notification\ReminderDelay;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * Paramètres › Agenda: the reminder delay, saved as it changes; the future
 * reminders not requested yet follow it (AC 22b).
 */
final class AgendaSettingsTest extends WebTestCase
{
    use Factories;

    private const string HOST = 'http://clinic.kiveto.local';

    private KernelBrowser $browser;
    private string $token = '';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        ClinicEntityFactory::new()->withId(Appointments::CLINIC)->withName('Clinique Montmartre')->create(['timeZone' => 'Europe/Paris']);
        $admin = ClinicUserFactory::new()->withEmail('claire.admin@example.com')->withPlainPassword('mot-de-passe')->create();
        ClinicMembershipEntityFactory::new()->withClinicId(Appointments::CLINIC)->withUserId($admin->getId()->toRfc4122())->asManager()->asEmployee()->asClinicAdministrator()->create();

        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"email":"claire.admin@example.com","password":"mot-de-passe"}');
        self::assertResponseIsSuccessful();
    }

    public function testTheReminderDelayIsChangedAndFutureRemindersFollow(): void
    {
        $this->browser->request('GET', self::HOST . '/settings/agenda');
        self::assertResponseIsSuccessful();
        $page = (string) $this->browser->getResponse()->getContent();
        self::assertStringContainsString('&quot;ag.rappel&quot;:&quot;H24&quot;', $page);
        self::assertSame(1, preg_match('/&quot;token&quot;:&quot;([^&]+)&quot;/', $page, $match));
        $this->token = $match[1];

        // Tuesday 05/03/2030 09:30 Paris (08:30 UTC), its reminder planned 24 h before.
        $appointments = self::getContainer()->get(AppointmentRepositoryInterface::class);
        \assert($appointments instanceof AppointmentRepositoryInterface);
        $appointments->save(Appointments::planned('2030-03-05 08:30'));
        $states = $this->states();
        $state  = $states->lock(AppointmentId::fromString(Appointments::ID));
        \assert(null !== $state);
        $state->planReminder(ReminderDelay::H24, Appointments::utc('2030-03-05 08:30'), new \DateTimeZone('Europe/Paris'), Appointments::utc('2026-10-26 10:00'));
        $states->save($state);

        $this->save(['ag.rappel' => 'SAME_DAY_8']);
        self::assertResponseIsSuccessful();

        self::assertSame('2030-03-05 07:00', $states->findForUpdate(AppointmentId::fromString(Appointments::ID))?->reminderDueAt()?->format('Y-m-d H:i'));
        $this->browser->request('GET', self::HOST . '/settings/agenda');
        self::assertStringContainsString('&quot;ag.rappel&quot;:&quot;SAME_DAY_8&quot;', (string) $this->browser->getResponse()->getContent());

        $this->save(['ag.rappel' => 'H72']);
        self::assertResponseStatusCodeSame(422);
    }

    /**
     * @param array<string, string> $values
     */
    private function save(array $values): void
    {
        $this->browser->request('POST', self::HOST . '/settings/agenda', ['_token' => $this->token, 'values' => json_encode($values, \JSON_THROW_ON_ERROR)], server: ['HTTP_ACCEPT' => 'application/json']);
    }

    private function states(): AppointmentNotificationStateRepositoryInterface
    {
        $states = self::getContainer()->get(AppointmentNotificationStateRepositoryInterface::class);
        \assert($states instanceof AppointmentNotificationStateRepositoryInterface);

        return $states;
    }
}
