<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic\PortalRequest;

use App\Fixtures\Context\Animal\Factory\WeightRecordEntityFactory;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use Symfony\Component\Uid\Uuid;

/**
 * « Demandes Portal » in VetApp (Task 42): the queue, the detail of a change
 * (AC 67 VetApp part) and of a renewal, the dashboard tasks (AC 71, 242),
 * the navigation badge, the permissions (AC 240) and the owner's text kept
 * inert (AC 239).
 */
final class PortalRequestsControllerTest extends PortalRequestTestCase
{
    public function testTheQueueListsTheRequestsWaitingForAnAnswer(): void
    {
        $change  = $this->change('name', 'Rex II', 'Faute de frappe');
        $renewal = $this->renewal('adverse_effects', 'Il vomit depuis deux jours');
        $this->signIn();

        $html = $this->page('/portal-requests');
        self::assertResponseIsSuccessful();
        // The badge of the navigation counts them.
        self::assertMatchesRegularExpression('#Demandes<span class="badge">2</span>#', $html);

        $payload  = $this->payload('/portal-requests');
        $register = self::arr($payload['register']);
        $rows     = self::rowsOf($register);

        // The urgent renewal first, then the oldest.
        self::assertSame([$renewal, $change], array_column($rows, 'id'));
        self::assertSame(['Renouvellement', 'Modification'], array_column($rows, 'short'));
        self::assertSame(['cls' => 'et-crit', 'label' => 'Effets indésirables'], $rows[0]['condition']);
        self::assertTrue($rows[0]['urgent']);
        self::assertSame(['Faute de frappe', 'Sophie Dupont', 'Nom'], [$rows[1]['note'], $rows[1]['clientName'], $rows[1]['subject']]);
        self::assertSame(['/portal-requests/' . $change, '/portal-requests/' . $change . '/sheet'], [$rows[1]['url'], $rows[1]['sheet']]);
        self::assertSame(['total' => 2, 'page' => 1, 'pp' => 25], ['total' => $register['total'], 'page' => $register['page'], 'pp' => $register['pp']]);
        // « À traiter » does not read the answered requests (REG-03)
        self::assertSame(['submitted' => 2, 'done' => null, 'rejected' => null, 'tout' => null], $register['counts']);
        self::assertSame(['pet_change' => 1, 'prescription_renewal' => 1, 'client_link' => 0, 'booking_request' => 0], $register['kinds']);
        $head = self::arr($register['head']);
        self::assertSame([2, 1, 0, false], [$head['pending'], $head['urgent'], $head['late'], $head['blank']]);
        self::assertSame($renewal, self::arr(self::arr($head['first'])['urgent'])['id']);
        self::assertSame(['statut' => 'submitted', 'type' => '', 'q' => '', 'page' => 1, 'pp' => 25], $payload['filters']);
        self::assertNull($payload['sheet']);
        self::assertSame(['request', 'link', 'booking'], array_keys(self::arr($payload['tokens'])));

        // Nothing answered yet, and the renewals filter keeps the renewal only.
        self::assertSame([], $this->ids('/portal-requests?statut=done'));
        self::assertSame([$renewal], $this->ids('/portal-requests?statut=submitted&type=prescription_renewal'));
    }

    public function testAWeightRequestShowsTheThreeValuesAndOffersTheWeighingForm(): void
    {
        WeightRecordEntityFactory::new()->forAnimal(self::CLINIC, $this->rex)->weighing(11800, new \DateTimeImmutable('2026-09-01'))->create();
        $id = $this->change('weight', '12,4', measuredOn: '2026-11-01');
        WeightRecordEntityFactory::new()->forAnimal(self::CLINIC, $this->rex)->weighing(12000, new \DateTimeImmutable('2026-11-02'))->create();
        $this->signIn();

        $sheet = $this->sheet('/portal-requests/' . $id);

        // On file now (and when asked), proposed.
        self::assertSame(['atSubmit' => '11,8 kg', 'current' => '12 kg', 'proposed' => '12,4 kg', 'gap' => ['label' => '+3,3 %', 'strong' => false]], $sheet['values']);
        self::assertSame('1 novembre 2026', $sheet['measuredOn']);
        self::assertSame('/animals/' . $this->rex . '?pesee=12,4&pesee_le=2026-11-01', $sheet['weighUrl']);
        self::assertSame(['resolve' => '/portal-requests/' . $id . '/resolve'], $sheet['actions']);
        self::assertTrue($sheet['canResolve']);

        // The animal sheet carries the hidden author field the link fills in.
        $animal = $this->browser->request('GET', self::HOST . '/animals/' . $this->rex . '?pesee=12%2C4&pesee_le=2026-11-01');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $animal->filter('#wForm input[name="author"]'));
    }

    public function testARenewalOffersThePetsPrescriptionsIssuedAfterTheRequest(): void
    {
        $id     = $this->renewal('no_improvement');
        $before = $this->prescription($this->rex, '2026-10-20 09:00:00');
        $after  = $this->prescription($this->rex, '2026-11-02 10:30:00');
        $other  = $this->prescription($this->pet('Nala'), '2026-11-02 10:40:00');
        $this->signIn();

        $sheet = $this->sheet('/portal-requests/' . $id);

        $candidates = array_column(array_map(self::arr(...), self::arr($sheet['candidates'])), 'id');
        self::assertSame([$after], $candidates);
        self::assertNotContains($before, $candidates);
        self::assertNotContains($other, $candidates);
        self::assertSame('Pas d’amélioration', $sheet['conditionLabel']);
        self::assertSame('Meloxidyl · 1', self::arr($sheet['prescription'])['medication']);
    }

    public function testTheRenewalFindsThePetsPrescriptionBehindTheClinicsBusyDay(): void
    {
        $id    = $this->renewal('improved');
        $after = $this->prescription($this->rex, '2026-11-02 10:30:00');
        $nala  = $this->pet('Nala');

        // 25 more recent ordonnances of another pet used to fill the page read
        for ($minute = 10; $minute < 35; ++$minute) {
            $this->prescription($nala, '2026-11-02 11:' . $minute . ':00');
        }

        $this->signIn();

        $sheet = $this->sheet('/portal-requests/' . $id);

        self::assertSame([$after], array_column(array_map(self::arr(...), self::arr($sheet['candidates'])), 'id'));
    }

    public function testTheDashboardShowsFourTasksAndSumsUpTheRest(): void
    {
        $urgent = $this->renewal('adverse_effects');
        foreach (['Mokka', 'Moka', 'Moko', 'Mika', 'Miko'] as $name) {
            $this->change('name', $name, petId: $this->pet('Chat ' . $name));
        }
        $this->signIn();

        $crawler = $this->browser->request('GET', self::HOST . '/dashboard');
        self::assertResponseIsSuccessful();

        $portal = $crawler->filter('.tk')->reduce(static fn ($node): bool => str_contains((string) $node->filter('a.go')->attr('href'), '/portal-requests'));
        self::assertCount(5, $portal, '4 requests and the « N autres demandes Portal » line');

        // AC 242: the renewal with adverse effects comes first, urgent, for the veterinarians.
        $first = $portal->first();
        self::assertSame('urgent', $first->attr('data-grp'));
        self::assertSame('veto', $first->attr('data-vis'));
        self::assertSame('/portal-requests/' . $urgent, $first->filter('a.go')->attr('href'));

        // A change request is for the assistants.
        self::assertSame(['jour', 'asv'], [$portal->eq(1)->attr('data-grp'), $portal->eq(1)->attr('data-vis')]);

        $last = $portal->last();
        self::assertStringContainsString('2 autres demandes Portal', $last->text());
        self::assertSame('/portal-requests', $last->filter('a.go')->attr('href'));
    }

    public function testWithoutManageAnimalsTheQueueIsRefused(): void
    {
        $id = $this->change('name', 'Rex II');
        $this->withoutPermissions(ClinicPermissionGuard::MANAGE_ANIMALS);
        $this->signIn();

        foreach (['/portal-requests', '/portal-requests/' . $id, '/portal-requests/search', '/portal-requests/' . $id . '/sheet'] as $path) {
            $this->page($path);
            self::assertResponseStatusCodeSame(403, $path);
        }

        // No navigation entry either.
        $html = $this->page('/dashboard');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('href="/portal-requests"', $html);
    }

    public function testAnotherClinicsOrAnUnknownRequestIsNotFound(): void
    {
        $id = $this->change('name', 'Rex II');
        $this->connection()->executeStatement('UPDATE client_portal__pet_change_requests SET clinic_id = UUID_TO_BIN(?) WHERE id = UUID_TO_BIN(?)', [self::OTHER, $id]);
        $this->signIn();

        foreach ([$id, Uuid::v7()->toRfc4122()] as $requestId) {
            $this->page('/portal-requests/' . $requestId);
            self::assertResponseStatusCodeSame(404);
            $this->page('/portal-requests/' . $requestId . '/sheet');
            self::assertResponseStatusCodeSame(404);
        }

        self::assertSame([], $this->ids('/portal-requests?statut=tout'));
    }

    /** AC 239: the owner's note stays text in the queue and the detail, and never reaches the bell. */
    public function testTheOwnersNoteIsRenderedInert(): void
    {
        $id = $this->change('other', 'Robe', self::XSS);
        $this->signIn();

        foreach (['/portal-requests', '/portal-requests/' . $id] as $path) {
            $html = $this->page($path);
            self::assertResponseIsSuccessful();
            self::assertStringNotContainsString(self::XSS, $html, $path);
            self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html, $path);
        }

        // Handed as text to the page module, which writes it through esc().
        self::assertSame(self::XSS, $this->sheet('/portal-requests/' . $id)['note']);
        self::assertSame(self::XSS, $this->rows('/portal-requests')[0]['note']);

        $bell = $this->connection()->fetchAllAssociative('SELECT title, detail FROM notification__in_app_notifications WHERE clinic_id = UUID_TO_BIN(?)', [self::CLINIC]);
        self::assertNotEmpty($bell);
        foreach ($bell as $row) {
            $text = (\is_string($row['title']) ? $row['title'] : '') . (\is_string($row['detail']) ? $row['detail'] : '');
            self::assertStringNotContainsString('<img', $text);
            self::assertStringNotContainsString('onerror', $text);
        }
    }
}
