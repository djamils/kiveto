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

        self::assertStringNotContainsString(self::XSS, $this->page('/portal-requests'));
        $rows = $this->rows('/portal-requests');

        self::assertCount(1, $rows);
        self::assertSame(
            ['id' => self::REQUEST, 'kind' => 'booking_request', 'short' => 'Nouveau client', 'clientName' => 'Marie Lenoir', 'isNew' => true, 'petName' => self::XSS, 'flags' => ['Nom différent', 'Déjà relié à un autre compte Portal']],
            self::pick($rows[0], 'id', 'kind', 'short', 'clientName', 'isNew', 'petName', 'flags'),
        );
        self::assertSame(['/portal-requests/nouveaux-clients/' . self::REQUEST, '/portal-requests/nouveaux-clients/' . self::REQUEST . '/sheet'], [$rows[0]['url'], $rows[0]['sheet']]);
        self::assertSame([self::REQUEST], $this->ids('/portal-requests?type=booking_request'));
        self::assertSame([], $this->ids('/portal-requests?type=pet_change'));
        self::assertSame([], $this->ids('/portal-requests?statut=done'));
    }

    /** AC 267. */
    public function testTheDetailShowsTheMatchingFilesWithTheirBadges(): void
    {
        $this->signIn();

        self::assertStringNotContainsString(self::XSS, $this->page('/portal-requests/nouveaux-clients/' . self::REQUEST));
        $sheet = $this->sheet('/portal-requests/nouveaux-clients/' . self::REQUEST);

        self::assertSame('jeu. 5 nov. · 10:30', $sheet['slotLong']);
        self::assertSame([self::XSS, 'Chat', 'Marie Lenoir', true, null], [$sheet['petName'], $sheet['petSpecies'], $sheet['ownerName'], $sheet['pending'], $sheet['attachedClientId']]);
        self::assertSame([
            'confirm' => '/portal-requests/nouveaux-clients/' . self::REQUEST . '/confirm',
            'decline' => '/portal-requests/nouveaux-clients/' . self::REQUEST . '/decline',
        ], $sheet['actions']);

        $matches = array_map(self::arr(...), self::arr($sheet['matches']));
        // Oldest file first
        self::assertSame([self::LENOIR, self::CLIENT], array_column($matches, 'clientId'));
        self::assertSame([false, false], [$matches[0]['mismatch'], $matches[0]['linkedElsewhere']], 'same last name, case aside');
        self::assertSame([true, true], [$matches[1]['mismatch'], $matches[1]['linkedElsewhere']]);
        $pets = array_map(static fn (array $m): array => array_map(self::arr(...), self::arr($m['pets'])), $matches);
        self::assertSame([[$this->moka], [$this->rex]], array_map(static fn (array $p): array => array_column($p, 'id'), $pets));
        self::assertSame('Moka (Chat)', $pets[0][0]['label']);

        // The same sheet, as JSON for the page module.
        self::assertSame($sheet, $this->json('/portal-requests/nouveaux-clients/' . self::REQUEST . '/sheet'));
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
        self::assertSame(['Confirmée', false], [($sheet = $this->sheet('/portal-requests/nouveaux-clients/' . self::REQUEST))['statusLabel'], $sheet['pending']]);
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

    public function testAnAnswerPostedInTheBackgroundIsJson(): void
    {
        $this->signIn();
        $token = $this->bookingToken();
        $json  = ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];
        $base  = self::HOST . '/portal-requests/nouveaux-clients/' . self::REQUEST;

        $this->browser->request('POST', $base . '/confirm', ['_token' => $token, 'client' => self::CLIENT, 'animal' => $this->moka], server: $json);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['success' => false, 'message' => 'Choisissez un animal de ce client, ou créez-le.'], $this->answered());

        $this->browser->request('POST', $base . '/confirm', ['_token' => $token, 'client' => self::LENOIR, 'animal' => $this->moka], server: $json);
        self::assertResponseIsSuccessful();
        self::assertSame(['success' => true, 'message' => 'Rendez-vous confirmé — le compte Portal est rattaché au dossier client.'], $this->answered());

        $this->browser->request('POST', $base . '/decline', ['_token' => $token], server: $json);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(['success' => false, 'message' => 'Demande déjà traitée.'], $this->answered());

        // The confirmed request leaves the queue; its sheet tells what became of it.
        self::assertSame([], $this->ids('/portal-requests'));
        self::assertSame(['done', false], [($sheet = $this->json('/portal-requests/nouveaux-clients/' . self::REQUEST . '/sheet'))['status'], $sheet['pending']]);
    }

    public function testDecliningInTheBackgroundIsJson(): void
    {
        $this->signIn();

        $this->browser->request('POST', self::HOST . '/portal-requests/nouveaux-clients/' . self::REQUEST . '/decline', ['_token' => $this->bookingToken()], server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        self::assertSame(['success' => true, 'message' => 'Demande refusée — le créneau est libéré et le propriétaire prévenu par SMS.'], $this->answered());
        self::assertSame(['declined'], $this->column('SELECT status FROM client_portal__booking_requests'));

        // S6-F3: listed under « Refusées » with who answered, as its sheet says.
        [$by] = $this->column('SELECT resolved_by_name FROM client_portal__booking_requests');
        self::assertNotSame('', $by);
        self::assertSame([], $this->ids('/portal-requests'));
        $rows = $this->rows('/portal-requests?statut=rejected');
        self::assertSame(
            [['id' => self::REQUEST, 'kind' => 'booking_request', 'status' => 'rejected', 'statusLabel' => 'Refusée', 'resolvedBy' => $by, 'flags' => [], 'age' => null]],
            array_map(static fn (array $row): array => self::pick($row, 'id', 'kind', 'status', 'statusLabel', 'resolvedBy', 'flags', 'age'), $rows),
        );
        $sheet = $this->json('/portal-requests/nouveaux-clients/' . self::REQUEST . '/sheet');
        self::assertSame([$by, 'rejected', false], [$sheet['resolvedBy'], $sheet['status'], $sheet['pending']]);
        self::assertIsString($sheet['resolvedAt']);
    }

    /** No staff member answered an expired request: « Système ». */
    public function testAnExpiredRequestIsListedUnderRefuseesAsTheSystems(): void
    {
        $this->signIn();
        $this->connection()->executeStatement("UPDATE client_portal__booking_requests SET status = 'declined', decline_reason = 'expired', resolved_at = '2026-11-02 08:00:00'");

        $rows = $this->rows('/portal-requests?statut=rejected');
        self::assertSame([[self::REQUEST, 'Système · délai expiré']], array_map(static fn (array $row): array => [$row['id'], $row['resolvedBy']], $rows));
        // The sheet names no one: its status says what ended it.
        self::assertSame([null, 'Expirée sans réponse'], [($sheet = $this->json('/portal-requests/nouveaux-clients/' . self::REQUEST . '/sheet'))['resolvedBy'], $sheet['statusLabel']]);
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
        $this->page('/portal-requests/nouveaux-clients/' . Uuid::v7()->toRfc4122() . '/sheet');
        self::assertResponseStatusCodeSame(404);
        $this->browser->request('POST', self::HOST . '/portal-requests/nouveaux-clients/' . Uuid::v7()->toRfc4122() . '/confirm', ['_token' => $token, 'client' => 'new', 'animal' => 'new']);
        self::assertResponseStatusCodeSame(404);
        $this->browser->request('POST', self::HOST . '/portal-requests/nouveaux-clients/' . Uuid::v7()->toRfc4122() . '/decline', ['_token' => $token]);
        self::assertResponseStatusCodeSame(404);

        $this->withoutPermissions(ClinicPermissionGuard::MANAGE_ANIMALS);
        $this->page('/portal-requests/nouveaux-clients/' . self::REQUEST);
        self::assertResponseStatusCodeSame(403);
        $this->page('/portal-requests/nouveaux-clients/' . self::REQUEST . '/sheet');
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

    /**
     * @return array<array-key, mixed>
     */
    private function answered(): array
    {
        return self::arr(json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    private function bookingToken(): string
    {
        $token = self::arr($this->payload('/portal-requests/nouveaux-clients/' . self::REQUEST)['tokens'])['booking'];
        \assert(\is_string($token));

        return $token;
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
