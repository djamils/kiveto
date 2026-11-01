<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic\Scheduling;

use App\Context\Animal\Domain\ValueObject\OwnershipRole;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Animal\Factory\OwnershipEntityFactory;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Presentation\Clinic\ViewModel\Dashboard\DashboardViewBuilder;
use App\Presentation\Clinic\ViewModel\Flux\FluxBoardViewBuilder;
use App\Shared\Domain\Time\ClockInterface;
use App\Tests\Shared\Time\FrozenClock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * AC 31: an online request waiting for the clinic shows « à confirmer » and
 * holds its slot on every VetApp screen; a declined one reads as cancelled
 * and is never counted. One test per screen: agenda (range JSON), dashboard,
 * flux board, shell « RDV aujourd'hui », client sheet, animal sheet.
 */
final class PendingAppointmentScreensTest extends WebTestCase
{
    use Factories;

    private const string CLINIC   = '01960000-0000-7000-8000-0000000a3c01';
    private const string CLIENT   = '01960000-0000-7000-8000-0000000a3d01';
    private const string PENDING  = '01960000-0000-7000-8000-0000000a3a01';
    private const string DECLINED = '01960000-0000-7000-8000-0000000a3a02';
    private const string HOST     = 'http://clinic.kiveto.local';

    private KernelBrowser $browser;
    private \DateTimeZone $tz;
    private \DateTimeImmutable $now;
    private string $vetId    = '';
    private string $animalId = '';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        // Today, 10:00 in the clinic: both bookings are later today.
        $this->tz  = new \DateTimeZone('Europe/Paris');
        $this->now = new \DateTimeImmutable('today 10:00', $this->tz);
        self::getContainer()->set(ClockInterface::class, new FrozenClock($this->now));

        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique des Tilleuls')->create(['timeZone' => 'Europe/Paris']);
        $user        = ClinicUserFactory::new()->withEmail('pending.vet@example.com')->withPlainPassword('mot-de-passe')->create();
        $this->vetId = $user->getId()->toRfc4122();
        ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($this->vetId)->asVeterinary()->asEmployee()->create();

        ClientEntityFactory::new()->withId(self::CLIENT)->withClinicId(self::CLINIC)->withName('Lucie', 'Garnier')->active()->create();
        $animalId = Uuid::v7();
        AnimalEntityFactory::new()->withClinicId(self::CLINIC)->withName('Pixel')->dog()->active()->alive()->create(['id' => $animalId]);
        OwnershipEntityFactory::createOne([
            'animal'   => AnimalEntityFactory::find(['id' => $animalId]),
            'clientId' => Uuid::fromString(self::CLIENT),
            'role'     => OwnershipRole::PRIMARY,
        ]);
        $this->animalId = $animalId->toRfc4122();

        $this->booking(self::PENDING, '14:00')->pending($this->at('12:00'))->create();
        $this->booking(self::DECLINED, '15:00')->declined()->create();

        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"email":"pending.vet@example.com","password":"mot-de-passe"}');
        self::assertResponseIsSuccessful();
    }

    public function testTheAgendaRangeShowsThePendingRequestAsAwaitingAndTheDeclinedOneAsCancelled(): void
    {
        $day = $this->now->format('Y-m-d');
        $this->browser->request('GET', self::HOST . '/scheduling/agenda/range?from=' . $day . '&to=' . $day);
        self::assertResponseIsSuccessful();

        $range = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($range) && \is_array($range['appointments']));

        $status = [];
        foreach ($range['appointments'] as $appointment) {
            \assert(\is_array($appointment) && \is_string($appointment['id']));
            $status[$appointment['id']] = $appointment['st'];
        }

        // `attente` is busy for agenda.js (only `annule` frees a slot)
        self::assertSame([self::PENDING => 'attente', self::DECLINED => 'annule'], $status);
    }

    public function testTheDashboardShowsThePendingRequestToConfirmAndLeavesOutTheDeclinedOne(): void
    {
        $builder = self::getContainer()->get(DashboardViewBuilder::class);
        \assert($builder instanceof DashboardViewBuilder);

        $view = $builder->build(self::CLINIC, $this->vetId);

        $rows = [];
        foreach ($view->timeline as $slot) {
            foreach ($slot->appointments as $row) {
                $rows[$row->id] = [$row->state, $row->tag];
            }
        }

        self::assertSame([self::PENDING => ['pending', 'À confirmer']], $rows);
        self::assertSame(1, $view->appointmentCount);

        $this->browser->request('GET', self::HOST . '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('RDV à <b>14:00</b> · à confirmer', (string) $this->browser->getResponse()->getContent());
    }

    public function testTheFluxBoardPlansThePendingRequestWithABadge(): void
    {
        $builder = self::getContainer()->get(FluxBoardViewBuilder::class);
        \assert($builder instanceof FluxBoardViewBuilder);

        $view = $builder->build(self::CLINIC);

        $planned = [];
        foreach ($view->planned as $slot) {
            foreach ($slot->patients as $patient) {
                $planned[(string) $patient->appointmentId] = $patient->isPending;
            }
        }

        self::assertSame([self::PENDING => true], $planned);
        self::assertSame(1, $view->plannedCount);

        $this->browser->request('GET', self::HOST . '/admission/queue');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('<span class="puce">À confirmer</span>', (string) $this->browser->getResponse()->getContent());
    }

    public function testTheShellCountsThePendingRequestButNotTheDeclinedOne(): void
    {
        $this->browser->request('GET', self::HOST . '/dashboard');
        self::assertResponseIsSuccessful();

        self::assertStringContainsString('<b>1</b> RDV aujourd\'hui', (string) $this->browser->getResponse()->getContent());
    }

    public function testTheClientSheetListsThePendingRequestToConfirm(): void
    {
        self::assertSame(['r-' . self::PENDING => ['avenir', true]], $this->appointmentVisits($this->sheet('/clients/' . self::CLIENT)));
    }

    public function testTheAnimalSheetListsThePendingRequestToConfirm(): void
    {
        self::assertSame(['r-' . self::PENDING => ['avenir', true]], $this->appointmentVisits($this->sheet('/animals/' . $this->animalId)));
    }

    /**
     * The `data-sheet` payload the page module renders.
     *
     * @return array<mixed>
     */
    private function sheet(string $path): array
    {
        $crawler = $this->browser->request('GET', self::HOST . $path);
        self::assertResponseIsSuccessful();

        $sheet = json_decode((string) $crawler->filter('[data-sheet]')->attr('data-sheet'), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($sheet));

        return $sheet;
    }

    /**
     * @param array<mixed> $sheet
     *
     * @return array<string, array{mixed, mixed}> booking rows of the sheet: id → [st, attente]
     */
    private function appointmentVisits(array $sheet): array
    {
        \assert(\is_array($sheet['visits']));

        $rows = [];
        foreach ($sheet['visits'] as $visit) {
            \assert(\is_array($visit) && \is_string($visit['id']));

            if (str_starts_with($visit['id'], 'r-')) {
                $rows[$visit['id']] = [$visit['st'], $visit['attente']];
            }
        }

        return $rows;
    }

    private function booking(string $id, string $time, int $minutes = 20): AppointmentEntityFactory
    {
        return AppointmentEntityFactory::new()
            ->withId($id)
            ->withClinicId(self::CLINIC)
            ->withPractitionerUserId($this->vetId)
            ->withOwnerAndAnimal(self::CLIENT, $this->animalId)
            ->withReason('Vaccination')
            ->startingAt($this->at($time), $minutes)
        ;
    }

    private function at(string $time): \DateTimeImmutable
    {
        return $this->now->modify($time)->setTimezone(new \DateTimeZone('UTC'));
    }
}
