<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic\QueryBudget;

use App\Context\ClientPortal\Application\Command\SubmitPetChangeRequest\SubmitPetChangeRequest;
use App\Context\Consultation\Domain\ValueObject\ConsultationStatus;
use App\Fixtures\Context\Admission\Factory\AdmissionEntityFactory;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Animal\Factory\OwnershipEntityFactory;
use App\Fixtures\Context\Animal\Factory\WeightRecordEntityFactory;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Client\Factory\ContactMethodEntityFactory;
use App\Fixtures\Context\ClientPortal\Factory\ClientLinkEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Consultation\Factory\ConsultationEntityFactory;
use App\Fixtures\Context\Patient\Factory\PatientEntityFactory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Support\Database\QueryCounter;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * The query budgets of the clinic pages: how many SQL statements a page may
 * run on a working day, and how many each patient of the day may add to it.
 *
 * Each patient of the day is a client with a phone and a Portal account, a
 * dog with a weighing and a visit last month, booked this morning, arrived
 * and in the waiting room, and a pet change request waiting in « Demandes
 * Portal ». The first one is also in consultation.
 *
 * The budgets were measured on 2026-11-05, before the performance work: a
 * page that runs more statements has regressed; a change that makes it run
 * fewer lowers its budget here, so that the gain stays.
 */
final class ClinicPageQueryBudgetTest extends WebTestCase
{
    use Factories;

    private const string CLINIC = '01960000-0000-7000-8000-0000000e9c01';
    private const string HOST   = 'http://clinic.kiveto.local';

    /** Patients of the day the budgets are measured with; the growth is measured by doubling them. */
    private const int PATIENTS = 3;

    private KernelBrowser $browser;
    private \DateTimeImmutable $now;
    private string $vetId = '';
    private int $patients = 0;

    /** The consultation the first patient is in. */
    private string $consultation = '';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        // Monday 2 November 2026, 11:00 in Paris.
        $this->now = new \DateTimeImmutable('2026-11-02 10:00:00', new \DateTimeZone('UTC'));
        self::getContainer()->set(ClockInterface::class, new FrozenClock($this->now));

        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique des Tilleuls')->create(['timeZone' => 'Europe/Paris']);
        $vet         = ClinicUserFactory::new()->withEmail('budget.vet@example.com')->withPlainPassword('mot-de-passe')->create();
        $this->vetId = $vet->getId()->toRfc4122();
        ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($this->vetId)->asVeterinary()->asEmployee()->create();

        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"email":"budget.vet@example.com","password":"mot-de-passe"}');
        self::assertResponseIsSuccessful();
    }

    /**
     * @param string $path       the page, `{consultation}` standing for the first patient's consultation
     * @param int    $budget     the most statements the page may run with PATIENTS patients
     * @param int    $perPatient the most statements each patient of the day may add
     */
    #[DataProvider('provideThePageStaysWithinItsQueryBudgetCases')]
    public function testThePageStaysWithinItsQueryBudget(string $path, int $budget, int $perPatient): void
    {
        $this->addPatients(self::PATIENTS);
        $base = $this->queriesOf($path);

        $this->addPatients(self::PATIENTS);
        $grown = $this->queriesOf($path);

        $report = \sprintf(
            "%s ran %d SQL statements with %d patients and %d with %d (%+d per patient); its budget is %d, and %d per patient.\nMost run with %d patients:\n%s",
            $path,
            $base,
            self::PATIENTS,
            $grown,
            2 * self::PATIENTS,
            intdiv($grown - $base, self::PATIENTS),
            $budget,
            $perPatient,
            2 * self::PATIENTS,
            self::mostRun(),
        );

        // Nothing counted means the middleware is not in the container: a
        // budget would then pass whatever the page runs.
        self::assertGreaterThan(0, $base, 'No SQL statement counted: QueryCounter is not registered (config/services_test.yaml), or the test container cache is stale (bin/console cache:clear --env=test).');
        self::assertLessThanOrEqual($budget, $base, $report);
        self::assertLessThanOrEqual($perPatient * self::PATIENTS, $grown - $base, $report);
    }

    /**
     * No page grows with the day: what a page shows of its patients is read
     * for all of them at once.
     *
     * @return iterable<string, array{string, int, int}>
     */
    public static function provideThePageStaysWithinItsQueryBudgetCases(): iterable
    {
        yield 'dashboard' => ['/dashboard', 53, 0];
        yield 'admission queue' => ['/admission/queue', 30, 0];
        yield 'consultation register' => ['/consultations', 33, 0];
        yield 'consultation' => ['/clinic/consultations/{consultation}', 59, 0];
        yield 'client directory' => ['/clients', 21, 0];
        yield 'prescriptions' => ['/prescriptions', 26, 0];
        yield 'agenda' => ['/scheduling/agenda', 20, 0];
        yield 'planning' => ['/scheduling/planning', 16, 0];
    }

    /** The statements the last request ran more than once, the most repeated first. */
    private static function mostRun(): string
    {
        $lines = [];

        foreach (\array_slice(QueryCounter::statements(), 0, 12, true) as $statement => $times) {
            if ($times > 1) {
                $lines[] = \sprintf('%4d× %s', $times, mb_substr($statement, 0, 180));
            }
        }

        return [] === $lines ? '  (none repeated)' : implode("\n", $lines);
    }

    /** The SQL statements the page runs, from the request to the rendered response. */
    private function queriesOf(string $path): int
    {
        QueryCounter::reset();
        $this->browser->request('GET', self::HOST . str_replace('{consultation}', $this->consultation, $path));
        self::assertResponseIsSuccessful();

        return QueryCounter::count();
    }

    private function addPatients(int $count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $this->addPatient(++$this->patients);
        }
    }

    private function addPatient(int $n): void
    {
        $clientId    = Uuid::v7()->toRfc4122();
        $animalId    = Uuid::v7()->toRfc4122();
        $patientId   = Uuid::v7()->toRfc4122();
        $admissionId = Uuid::v7()->toRfc4122();

        ClientEntityFactory::new()->withId($clientId)->withClinicId(self::CLINIC)->withName('Client', 'N' . $n)->withCivility('madam')->active()->create();
        ContactMethodEntityFactory::new()->forClient($clientId)->phone(\sprintf('+3361234%04d', $n))->mobile()->primary()->create();

        AnimalEntityFactory::new()->withClinicId(self::CLINIC)->withName('Animal ' . $n)->dog()->active()->alive()->create(['id' => Uuid::fromString($animalId)]);
        OwnershipEntityFactory::new()->withAnimalId($animalId)->withClientId($clientId)->primary()->active()->create();
        WeightRecordEntityFactory::new()->forAnimal(self::CLINIC, $animalId)->weighing(12000 + $n, $this->now->modify('-10 days'))->create();
        PatientEntityFactory::new()->withId($patientId)->withClinicId(self::CLINIC)->identified()->withAnimalLinkId($animalId)->active()->create();

        // Last month's visit.
        ConsultationEntityFactory::new()
            ->withClinicId(self::CLINIC)
            ->withPatientId($patientId)
            ->withPractitionerUserId($this->vetId)
            ->withAdmissionId(Uuid::v7()->toRfc4122())
            ->withStatus(ConsultationStatus::CLOSED)
            ->create(['startedAtUtc' => $this->now->modify('-30 days'), 'closedAtUtc' => $this->now->modify('-30 days +20 minutes')])
        ;

        // Booked this morning, arrived, in the waiting room.
        $arrived = $this->now->modify(\sprintf('-%d minutes', 60 - $n));
        AdmissionEntityFactory::new()->withId($admissionId)->withClinicId(self::CLINIC)->withPatientId($patientId)->active()->inWaitingRoom()->create([
            'openedAt'  => $arrived,
            'createdAt' => $arrived,
            'updatedAt' => $arrived,
        ]);
        AppointmentEntityFactory::new()
            ->withClinicId(self::CLINIC)
            ->withPractitionerUserId($this->vetId)
            ->withOwnerAndAnimal($clientId, $animalId)
            ->withReason('Consultation')
            ->withLinkedAdmissionId($admissionId)
            ->startingAt($this->now->modify(\sprintf('-%d minutes', 60 - $n)), 30)
            ->create()
        ;

        if (1 === $n) {
            $consultation = ConsultationEntityFactory::new()
                ->withClinicId(self::CLINIC)
                ->withPatientId($patientId)
                ->withPractitionerUserId($this->vetId)
                ->withAdmissionId($admissionId)
                ->withStatus(ConsultationStatus::OPEN)
                ->create(['startedAtUtc' => $this->now->modify('-10 minutes')])
            ;
            $this->consultation = Uuid::fromBinary($consultation->getId())->toRfc4122();
        }

        // A pet change waiting in « Demandes Portal ».
        $owner = PortalUserFactory::new()->withPhone(\sprintf('+3361299%04d', $n))->create();
        ClientLinkEntityFactory::new()->between($owner->getId()->toRfc4122(), self::CLINIC, $clientId)->linkedAt(new \DateTimeImmutable('2026-10-01'))->create();
        $commands = self::getContainer()->get(CommandBusInterface::class);
        \assert($commands instanceof CommandBusInterface);
        $commands->dispatch(new SubmitPetChangeRequest($owner->getId()->toRfc4122(), self::CLINIC, $animalId, 'name', 'Animal ' . $n . ' II', null, null));
    }
}
