<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic\PortalRequest;

use App\Context\Animal\Domain\ValueObject\OwnershipRole;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\ClientPortal\Domain\Booking\BookingRequest;
use App\Context\ClientPortal\Domain\Booking\BookingRequestId;
use App\Context\ClientPortal\Domain\Booking\BookingRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Animal\Factory\OwnershipEntityFactory;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Client\Factory\ContactMethodEntityFactory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use Symfony\Component\Uid\Uuid;

/**
 * « Demandes Portal » type « Nouveau client » (S7d, AC 80, AC 267): Marie
 * Lenoir's account (+33612349048, no link) asked the Tilleuls clinic for a
 * first appointment for « Pixel », a cat. Two files carry that mobile:
 * « Marie LENOIR » (Moka) and Sophie Dupont (another name, already linked
 * to her own account).
 */
final class BookingRequestTest extends PortalRequestTestCase
{
    private const string PHONE       = '+33612349048';
    private const string REQUEST     = '01960000-0000-7000-8000-0000000d6e48';
    private const string APPOINTMENT = '01960000-0000-7000-8000-0000000d6a48';
    private const string LENOIR      = '01960000-0000-7000-8000-0000000d6d48';

    private string $accountId = '';
    private string $moka      = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountId = PortalUserFactory::new()->withPhone(self::PHONE)->create()->getId()->toRfc4122();
        $this->service(OwnerProfileRepositoryInterface::class)->save(OwnerProfile::register($this->accountId, 'Marie', 'Lenoir', 'v1', new \DateTimeImmutable('2026-11-01')));

        ClientEntityFactory::new()->withId(self::LENOIR)->withClinicId(self::CLINIC)->withName('Marie', 'LENOIR')->since('2025-01-01')->active()->create();
        ContactMethodEntityFactory::new()->forClient(self::LENOIR)->phone(self::PHONE)->create(['label' => ContactLabel::MOBILE, 'isPrimary' => true]);
        ContactMethodEntityFactory::new()->forClient(self::CLIENT)->phone(self::PHONE)->create(['label' => ContactLabel::MOBILE, 'isPrimary' => false]);
        $moka = Uuid::v7();
        AnimalEntityFactory::new()->withClinicId(self::CLINIC)->withName('Moka')->cat()->active()->alive()->create(['id' => $moka]);
        OwnershipEntityFactory::createOne(['animal' => AnimalEntityFactory::find(['id' => $moka]), 'clientId' => Uuid::fromString(self::LENOIR), 'role' => OwnershipRole::PRIMARY]);
        $this->moka = $moka->toRfc4122();

        $this->bookingRequest(self::REQUEST, self::APPOINTMENT, $this->accountId, self::XSS, 'Marie', 'Lenoir', self::PHONE, '2026-11-05 09:30', '2026-11-02 09:00');
    }

    public function testTheQueueListsTheNewClientRequestWithItsBadges(): void
    {
        $this->signIn();

        $html = $this->page('/portal-requests');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('/portal-requests/nouveaux-clients/' . self::REQUEST, $html);
        self::assertStringContainsString('Nouveau client', $html);
        self::assertStringContainsString('Compte Portal : Marie Lenoir', $html);
        self::assertStringContainsString('Nom différent', $html);
        self::assertStringNotContainsString(self::XSS, $html);
        self::assertStringContainsString('/portal-requests/nouveaux-clients/', $this->page('/portal-requests?type=booking_request'));
        self::assertStringNotContainsString('/portal-requests/nouveaux-clients/', $this->page('/portal-requests?type=pet_change'));
        self::assertStringNotContainsString('/portal-requests/nouveaux-clients/', $this->page('/portal-requests?statut=done'));
    }

    /** AC 267. */
    public function testTheDetailShowsTheMatchingFilesWithTheirBadges(): void
    {
        $this->signIn();

        $crawler = $this->browser->request('GET', self::HOST . '/portal-requests/nouveaux-clients/' . self::REQUEST);

        self::assertResponseIsSuccessful();
        $html = (string) $this->browser->getResponse()->getContent();
        self::assertStringNotContainsString(self::XSS, $html);
        self::assertStringContainsString('5 nov. 2026 à 10:30', $html);

        $rows = $crawler->filter('.prq-files tbody tr');
        // Oldest file first
        self::assertSame([self::LENOIR, self::CLIENT, 'new'], $rows->each(static fn ($row): string => (string) $row->attr('data-client-id')));
        self::assertSame([], $rows->eq(0)->filter('[data-badge]')->each(static fn ($b): string => (string) $b->attr('data-badge')), 'same last name, case aside');
        self::assertStringContainsString('Moka (Chat)', $rows->eq(0)->text());
        self::assertSame(['mismatch', 'linked-elsewhere'], $rows->eq(1)->filter('[data-badge]')->each(static fn ($b): string => (string) $b->attr('data-badge')));
        self::assertSame([$this->moka, $this->rex, 'new'], $crawler->filter('#prqBookingConfirm input[name="animal"]')->each(static fn ($i): string => (string) $i->attr('value')));
    }

    /** AC 80: an existing file and animal; the link and the appointment follow; a second answer is refused. */
    public function testConfirmingWithAnExistingFileLinksTheAccountAndPlansTheAppointment(): void
    {
        $this->signIn();
        $token = $this->bookingToken();

        $this->answer('confirm', $token, ['client' => self::LENOIR, 'animal' => $this->moka]);

        self::assertResponseRedirects('/portal-requests', 303);
        self::assertStringContainsString('Rendez-vous confirmé', $this->flashes());
        self::assertSame(['confirmed'], $this->column('SELECT status FROM client_portal__booking_requests'));
        self::assertSame(['confirmed'], $this->column('SELECT status FROM client_portal__client_links WHERE user_id = ?', [Uuid::fromString($this->accountId)->toBinary()]));
        self::assertSame(
            ['status' => 'PLANNED', 'owner_id' => Uuid::fromString(self::LENOIR)->toBinary(), 'animal_id' => Uuid::fromString($this->moka)->toBinary()],
            $this->connection()->fetchAssociative('SELECT status, owner_id, animal_id FROM scheduling__appointments WHERE id = ?', [Uuid::fromString(self::APPOINTMENT)->toBinary()]),
        );

        $this->answer('decline', $token);
        self::assertResponseRedirects('/portal-requests/nouveaux-clients/' . self::REQUEST, 303);
        self::assertStringContainsString('Demande déjà traitée.', $this->flashes());
        self::assertStringContainsString('Confirmée', $this->page('/portal-requests/nouveaux-clients/' . self::REQUEST));
    }

    public function testConfirmingWithANewFileCreatesItAndThePet(): void
    {
        $this->signIn();

        $this->answer('confirm', $this->bookingToken(), ['client' => 'new', 'animal' => 'new']);

        self::assertResponseRedirects('/portal-requests', 303);
        self::assertSame(['Marie Lenoir'], $this->column("SELECT CONCAT(first_name, ' ', last_name) FROM client__clients WHERE clinic_id = ? AND last_name = 'Lenoir' AND id <> ?", [Uuid::fromString(self::CLINIC)->toBinary(), Uuid::fromString(self::LENOIR)->toBinary()]));
        self::assertSame([self::XSS], $this->column("SELECT name FROM animal__animals WHERE clinic_id = ? AND species = 'cat' AND name <> 'Moka'", [Uuid::fromString(self::CLINIC)->toBinary()]));
    }

    public function testAnAnimalOfAnotherFileIsRefused(): void
    {
        $this->signIn();

        $this->answer('confirm', $this->bookingToken(), ['client' => self::CLIENT, 'animal' => $this->moka]);

        self::assertResponseRedirects('/portal-requests/nouveaux-clients/' . self::REQUEST, 303);
        self::assertStringContainsString('Choisissez un animal de ce client', $this->flashes());
        self::assertSame(['pending'], $this->column('SELECT status FROM client_portal__booking_requests'));
    }

    public function testDecliningFreesTheSlot(): void
    {
        $this->signIn();

        $this->answer('decline', $this->bookingToken());

        self::assertResponseRedirects('/portal-requests', 303);
        self::assertStringContainsString('Demande refusée', $this->flashes());
        self::assertSame(['declined'], $this->column('SELECT status FROM client_portal__booking_requests'));
        self::assertSame(['DECLINED'], $this->column('SELECT status FROM scheduling__appointments WHERE id = ?', [Uuid::fromString(self::APPOINTMENT)->toBinary()]));
    }

    public function testTheTokenThePermissionAndTheClinicAreChecked(): void
    {
        $this->signIn();
        $token = $this->bookingToken();

        $this->answer('confirm', 'forged', ['client' => 'new', 'animal' => 'new']);
        self::assertResponseStatusCodeSame(403);

        $this->page('/portal-requests/nouveaux-clients/' . Uuid::v7()->toRfc4122());
        self::assertResponseStatusCodeSame(404);
        $this->browser->request('POST', self::HOST . '/portal-requests/nouveaux-clients/' . Uuid::v7()->toRfc4122() . '/confirm', ['_token' => $token, 'client' => 'new', 'animal' => 'new']);
        self::assertResponseStatusCodeSame(404);
        $this->browser->request('POST', self::HOST . '/portal-requests/nouveaux-clients/' . Uuid::v7()->toRfc4122() . '/decline', ['_token' => $token]);
        self::assertResponseStatusCodeSame(404);

        $this->withoutPermissions(ClinicPermissionGuard::MANAGE_ANIMALS);
        $this->page('/portal-requests/nouveaux-clients/' . self::REQUEST);
        self::assertResponseStatusCodeSame(403);
        $this->answer('decline', $token);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(['pending'], $this->column('SELECT status FROM client_portal__booking_requests'));
    }

    public function testAnExpiredRequestCannotBeConfirmed(): void
    {
        $this->signIn();
        $token = $this->bookingToken();
        $this->connection()->executeStatement('UPDATE scheduling__appointments SET pending_expires_at = ? WHERE id = ?', ['2026-11-02 09:00:00', Uuid::fromString(self::APPOINTMENT)->toBinary()]);

        $this->answer('confirm', $token, ['client' => self::LENOIR, 'animal' => $this->moka]);

        self::assertStringContainsString('Demande expirée.', $this->flashes());
        self::assertSame(['pending'], $this->column('SELECT status FROM client_portal__booking_requests'));
    }

    public function testTheDashboardAsksTheAssistantsToAnswerTheRequest(): void
    {
        $this->signIn();

        $crawler = $this->browser->request('GET', self::HOST . '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString(self::XSS, (string) $this->browser->getResponse()->getContent());

        $tasks = $crawler->filter('.tk')->reduce(static fn ($node): bool => str_contains((string) $node->filter('a.go')->attr('href'), '/portal-requests/nouveaux-clients/'));
        self::assertCount(1, $tasks);
        self::assertSame(['jour', 'asv'], [$tasks->attr('data-grp'), $tasks->attr('data-vis')]);
        self::assertSame('/portal-requests/nouveaux-clients/' . self::REQUEST, $tasks->filter('a.go')->attr('href'));
        self::assertStringContainsString('Nouveau client — Marie Lenoir', $tasks->text());
        self::assertStringContainsString('5 nov. à 10:30', $tasks->text());
        self::assertStringContainsString(self::XSS . ' (Chat)', $tasks->text(), 'the pet name as text');
        self::assertStringContainsString('nom différent · déjà relié à un autre compte · reçue à 10:00', $tasks->text());

        // Its pending slot is not offered to the agenda's Confirm / Refuse (refused for a new client)
        self::assertCount(0, $crawler->filter('.tk')->reduce(static fn ($node): bool => str_contains($node->text(), 'RDV en ligne à confirmer')));
    }

    public function testBeyondFourTheDashboardCountsTheOtherPortalRequestsTogether(): void
    {
        foreach (range(1, 4) as $n) {
            $phone   = '+3361234905' . $n;
            $account = PortalUserFactory::new()->withPhone($phone)->create()->getId()->toRfc4122();
            $this->bookingRequest(Uuid::v7()->toRfc4122(), Uuid::v7()->toRfc4122(), $account, 'Chat ' . $n, 'Léa', 'Martin ' . $n, $phone, \sprintf('2026-11-0%d 08:00', 4 + $n % 2), \sprintf('2026-11-02 09:%02d', 10 + $n));
        }
        $this->change('name', 'Rex II');
        $this->signIn();

        $crawler = $this->browser->request('GET', self::HOST . '/dashboard');
        self::assertResponseIsSuccessful();

        $portal = $crawler->filter('.tk')->reduce(static fn ($node): bool => str_starts_with((string) $node->filter('a.go')->attr('href'), '/portal-requests'));
        self::assertCount(6, $portal, 'the change request, 4 « Nouveau client » and the shared « +N » line');
        self::assertSame('/portal-requests/nouveaux-clients/' . self::REQUEST, $portal->eq(1)->filter('a.go')->attr('href'), 'the oldest first');

        $last = $portal->last();
        self::assertStringContainsString('1 autre demande Portal', $last->text());
        self::assertSame('/portal-requests', $last->filter('a.go')->attr('href'));
    }

    private function bookingToken(): string
    {
        $crawler = $this->browser->request('GET', self::HOST . '/portal-requests/nouveaux-clients/' . self::REQUEST);
        self::assertResponseIsSuccessful();

        return (string) $crawler->filter('#prqBookingConfirm input[name="_token"]')->attr('value');
    }

    /** One more account asking the clinic for a first appointment, with the pending slot it holds. */
    private function bookingRequest(string $id, string $appointmentId, string $accountId, string $petName, string $firstName, string $lastName, string $phone, string $startUtc, string $createdUtc): void
    {
        $utc = new \DateTimeZone('UTC');
        AppointmentEntityFactory::new()->withId($appointmentId)->withClinicId(self::CLINIC)->withPractitionerUserId($this->vetId)
            ->withOwnerAndAnimal(null, null)
            ->startingAt(new \DateTimeImmutable($startUtc, $utc))
            ->pending(new \DateTimeImmutable('2026-11-04 10:00', $utc))
            ->create()
        ;
        $this->service(BookingRequestRepositoryInterface::class)->save(BookingRequest::request(
            BookingRequestId::fromString($id),
            $accountId,
            self::CLINIC,
            '01960000-0000-7000-8000-0000000d6f48',
            $appointmentId,
            'Première consultation',
            new \DateTimeImmutable($startUtc, $utc),
            $petName,
            'cat',
            $firstName,
            $lastName,
            $phone,
            new \DateTimeImmutable($createdUtc, $utc),
        ));
    }

    /**
     * @param array<string, string> $fields
     */
    private function answer(string $action, string $token, array $fields = []): void
    {
        $this->browser->request('POST', self::HOST . '/portal-requests/nouveaux-clients/' . self::REQUEST . '/' . $action, ['_token' => $token, ...$fields]);
    }

    /**
     * @param list<mixed> $params
     *
     * @return list<string>
     */
    private function column(string $sql, array $params = []): array
    {
        return array_map(static fn (mixed $value): string => \is_string($value) ? $value : '', $this->connection()->fetchFirstColumn($sql, $params));
    }
}
