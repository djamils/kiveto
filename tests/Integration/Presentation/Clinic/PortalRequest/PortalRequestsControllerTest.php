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
        self::assertStringContainsString('Demandes Portal', $html);
        self::assertStringContainsString('/portal-requests/' . $change, $html);
        self::assertStringContainsString('/portal-requests/' . $renewal, $html);
        self::assertStringContainsString('Modification de fiche', $html);
        self::assertStringContainsString('Effets indésirables', $html);
        self::assertStringContainsString('Faute de frappe', $html);
        self::assertStringContainsString('Sophie Dupont', $html);

        // Nothing answered yet, and the renewals filter keeps the renewal only.
        self::assertStringContainsString('Aucune demande', $this->page('/portal-requests?statut=done'));
        $renewals = $this->page('/portal-requests?statut=submitted&type=prescription_renewal');
        self::assertStringContainsString($renewal, $renewals);
        self::assertStringNotContainsString($change, $renewals);

        // The badge of the navigation counts them.
        self::assertMatchesRegularExpression('#Demandes Portal<span class="badge">2</span>#', $html);
    }

    public function testAWeightRequestShowsTheThreeValuesAndOffersTheWeighingForm(): void
    {
        WeightRecordEntityFactory::new()->forAnimal(self::CLINIC, $this->rex)->weighing(11800, new \DateTimeImmutable('2026-09-01'))->create();
        $id = $this->change('weight', '12,4', measuredOn: '2026-11-01');
        WeightRecordEntityFactory::new()->forAnimal(self::CLINIC, $this->rex)->weighing(12000, new \DateTimeImmutable('2026-11-02'))->create();
        $this->signIn();

        $crawler = $this->browser->request('GET', self::HOST . '/portal-requests/' . $id);

        self::assertResponseIsSuccessful();
        self::assertSame(['Valeur à la demande', 'Valeur actuelle', 'Valeur proposée'], $crawler->filter('.prq-values span')->each(static fn ($node): string => $node->text()));
        self::assertSame(['11,8 kg', '12 kg', '12,4 kg'], $crawler->filter('.prq-values b')->each(static fn ($node): string => $node->text()));
        self::assertSame('pesé le 1 novembre 2026', $crawler->filter('.prq-values small')->text());
        self::assertSame('/animals/' . $this->rex . '?pesee=12,4&pesee_le=2026-11-01', $crawler->filter('.prq-hint a.btn')->attr('href'));

        // The animal sheet carries the hidden author field the link fills in.
        $sheet = $this->browser->request('GET', self::HOST . '/animals/' . $this->rex . '?pesee=12%2C4&pesee_le=2026-11-01');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $sheet->filter('#wForm input[name="author"]'));
    }

    public function testARenewalOffersThePetsPrescriptionsIssuedAfterTheRequest(): void
    {
        $id     = $this->renewal('no_improvement');
        $before = $this->prescription($this->rex, '2026-10-20 09:00:00');
        $after  = $this->prescription($this->rex, '2026-11-02 10:30:00');
        $other  = $this->prescription($this->pet('Nala'), '2026-11-02 10:40:00');
        $this->signIn();

        $crawler = $this->browser->request('GET', self::HOST . '/portal-requests/' . $id);

        self::assertResponseIsSuccessful();
        $options = $crawler->filter('select[name="resulting_prescription_id"] option')->each(static fn ($node): string => (string) $node->attr('value'));
        self::assertSame(['', $after], $options);
        self::assertNotContains($before, $options);
        self::assertNotContains($other, $options);
        self::assertStringContainsString('Pas d’amélioration', $crawler->text());
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

        $this->page('/portal-requests');
        self::assertResponseStatusCodeSame(403);
        $this->page('/portal-requests/' . $id);
        self::assertResponseStatusCodeSame(403);

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

        $this->page('/portal-requests/' . $id);
        self::assertResponseStatusCodeSame(404);
        $this->page('/portal-requests/' . Uuid::v7()->toRfc4122());
        self::assertResponseStatusCodeSame(404);
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

        $bell = $this->connection()->fetchAllAssociative('SELECT title, detail FROM notification__in_app_notifications WHERE clinic_id = UUID_TO_BIN(?)', [self::CLINIC]);
        self::assertNotEmpty($bell);
        foreach ($bell as $row) {
            $text = (\is_string($row['title']) ? $row['title'] : '') . (\is_string($row['detail']) ? $row['detail'] : '');
            self::assertStringNotContainsString('<img', $text);
            self::assertStringNotContainsString('onerror', $text);
        }
    }
}
