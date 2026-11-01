<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic;

use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Presentation\Clinic\ViewModel\Dashboard\DashboardTask;
use App\Presentation\Clinic\ViewModel\Dashboard\DashboardViewBuilder;
use App\Shared\Domain\Time\ClockInterface;
use App\Tests\Shared\Time\FrozenClock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * Online requests on the agenda and the dashboard (S4h, AC 39): the agenda
 * rows say which are pending and when they expire, the agenda posts
 * Confirmer / Refuser, and « RDV en ligne à confirmer » lists four of them
 * with their buttons, then « N autres », until they are decided.
 */
final class AgendaPendingAppointmentTest extends WebTestCase
{
    use Factories;

    private const string CLINIC  = '01960000-0000-7000-8000-0000000b7c01';
    private const string PLANNED = '01960000-0000-7000-8000-0000000b7a00';
    private const string HOST    = 'http://clinic.kiveto.local';

    private KernelBrowser $browser;
    private \DateTimeImmutable $now;
    private string $vetId = '';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        $this->now = new \DateTimeImmutable('today 10:00', new \DateTimeZone('Europe/Paris'));
        self::getContainer()->set(ClockInterface::class, new FrozenClock($this->now));

        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique des Tilleuls')->create(['timeZone' => 'Europe/Paris']);
        $user        = ClinicUserFactory::new()->withEmail('agenda.vet@example.com')->withPlainPassword('mot-de-passe')->create();
        $this->vetId = $user->getId()->toRfc4122();
        ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($this->vetId)->asVeterinary()->asEmployee()->create();

        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"email":"agenda.vet@example.com","password":"mot-de-passe"}');
        self::assertResponseIsSuccessful();
    }

    public function testTheAgendaRowsFlagThePendingRequestWithItsExpiry(): void
    {
        $this->booking(self::PLANNED, '+1 day 09:00')->create();
        $this->booking($this->pendingId(1), '+1 day 14:00')->pending($this->utc('+1 day 08:00'))->create();

        $day = $this->now->modify('+1 day')->format('Y-m-d');
        $this->browser->request('GET', self::HOST . '/scheduling/agenda/range?from=' . $day . '&to=' . $day);
        self::assertResponseIsSuccessful();

        $range = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($range) && \is_array($range['appointments']));

        $rows = [];
        foreach ($range['appointments'] as $row) {
            \assert(\is_array($row) && \is_string($row['id']));
            $rows[$row['id']] = [$row['st'], $row['pend'], $row['exp']];
        }

        // `exp` is clinic-local
        self::assertSame([
            self::PLANNED       => [null, false, null],
            $this->pendingId(1) => ['attente', true, $day . ' 08:00'],
        ], $rows);

        $this->browser->request('GET', self::HOST . '/scheduling/agenda');
        self::assertResponseIsSuccessful();
        $page = (string) $this->browser->getResponse()->getContent();
        self::assertStringContainsString('"confirm":"\/scheduling\/appointments\/__ID__\/confirm"', $page);
        self::assertStringContainsString('"decline":"\/scheduling\/appointments\/__ID__\/decline"', $page);
    }

    public function testTheDashboardListsFourRequestsWithTheirButtonsThenTheRest(): void
    {
        // Six requests over the coming days, the soonest to expire first; one planned booking.
        for ($i = 1; $i <= 6; ++$i) {
            $this->booking($this->pendingId($i), \sprintf('+%d days 14:00', $i))->pending($this->utc(\sprintf('+%d hours', $i)))->create();
        }
        $this->booking(self::PLANNED, '+1 day 09:00')->create();

        $tasks = $this->pendingTasks();
        self::assertCount(5, $tasks);
        self::assertSame(['jour', 'jour', 'jour', 'jour', 'jour'], array_map(static fn (DashboardTask $task): string => $task->group, $tasks));
        // No owner yet: a new client's request.
        self::assertSame('RDV en ligne à confirmer — nouveau client', $tasks[0]->title);
        self::assertStringContainsString('sans réponse, refusée à 11:00', $tasks[0]->subtitle);
        self::assertSame([
            ['label' => 'Confirmer', 'url' => '/scheduling/appointments/' . $this->pendingId(1) . '/confirm', 'danger' => false],
            ['label' => 'Refuser', 'url' => '/scheduling/appointments/' . $this->pendingId(1) . '/decline', 'danger' => true],
        ], $tasks[0]->forms);
        self::assertSame('2 autres RDV en ligne à confirmer', $tasks[4]->title);
        self::assertSame([], $tasks[4]->forms);

        $this->browser->request('GET', self::HOST . '/dashboard');
        self::assertResponseIsSuccessful();
        $page = (string) $this->browser->getResponse()->getContent();
        self::assertStringContainsString('action="/scheduling/appointments/' . $this->pendingId(1) . '/confirm"', $page);
        self::assertStringContainsString('action="/scheduling/appointments/' . $this->pendingId(1) . '/decline"', $page);
        self::assertStringContainsString('2 autres RDV en ligne à confirmer', $page);
    }

    public function testADecidedRequestLeavesTheDashboard(): void
    {
        $this->booking($this->pendingId(1), '+1 day 14:00')->pending($this->utc('+1 day 08:00'))->create();
        $this->booking($this->pendingId(2), '+2 days 14:00')->pending($this->utc('+1 day 09:00'))->create();
        self::assertCount(2, $this->pendingTasks());

        $crawler = $this->browser->request('GET', self::HOST . '/dashboard');
        $form    = $crawler->filter('form[action="/scheduling/appointments/' . $this->pendingId(1) . '/confirm"]')->form();
        $this->browser->submit($form);
        self::assertResponseRedirects('/dashboard');
        $this->browser->followRedirect();
        self::assertStringContainsString('RDV confirmé.', (string) $this->browser->getResponse()->getContent());

        $tasks = $this->pendingTasks();
        self::assertCount(1, $tasks);
        self::assertSame('/scheduling/appointments/' . $this->pendingId(2) . '/confirm', $tasks[0]->forms[0]['url']);
    }

    public function testWithoutAnyRequestThereIsNoTask(): void
    {
        $this->booking(self::PLANNED, '+1 day 09:00')->create();

        self::assertSame([], $this->pendingTasks());
    }

    /**
     * @return list<DashboardTask>
     */
    private function pendingTasks(): array
    {
        $builder = self::getContainer()->get(DashboardViewBuilder::class);
        \assert($builder instanceof DashboardViewBuilder);

        return array_values(array_filter(
            $builder->build(self::CLINIC, $this->vetId)->tasks,
            static fn (DashboardTask $task): bool => 'rdv' === $task->icon,
        ));
    }

    private function booking(string $id, string $start): AppointmentEntityFactory
    {
        return AppointmentEntityFactory::new()
            ->withId($id)
            ->withClinicId(self::CLINIC)
            ->withPractitionerUserId($this->vetId)
            ->withReason('Consultation')
            ->startingAt($this->utc($start), 30)
        ;
    }

    private function pendingId(int $n): string
    {
        return \sprintf('01960000-0000-7000-8000-0000000b7a%02d', $n);
    }

    private function utc(string $modifier): \DateTimeImmutable
    {
        return $this->now->modify($modifier)->setTimezone(new \DateTimeZone('UTC'));
    }
}
