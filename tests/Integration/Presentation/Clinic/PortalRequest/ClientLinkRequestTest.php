<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic\PortalRequest;

use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use Symfony\Component\Uid\Uuid;

/**
 * VetApp « Demandes Portal », type « Rattachement » (S7b, AC 76, 267): a
 * Portal account that accepted a link suggestion waits for the clinic. The
 * detail shows the Portal profile name next to each file's name and
 * animals, the mismatch and « déjà relié à un autre compte Portal » badges;
 * « Confirmer » / « Refuser » answer with CSRF, PRG and a flash.
 */
final class ClientLinkRequestTest extends PortalRequestTestCase
{
    private const string FAMILY = '01960000-0000-7000-8000-0000000d6d02';

    private string $accountId = '';

    protected function setUp(): void
    {
        parent::setUp();

        ClientEntityFactory::new()->withId(self::FAMILY)->withClinicId(self::CLINIC)->withName('Jean', 'DUPOND')->active()->create();
        $account         = PortalUserFactory::new()->withPhone('+33612349045')->create();
        $this->accountId = $account->getId()->toRfc4122();
        $this->service(OwnerProfileRepositoryInterface::class)->save(OwnerProfile::createFromClient($this->accountId, self::XSS, 'Dupond', null, 'v1', new \DateTimeImmutable('2026-11-01')));

        // Sophie Dupont's file is already confirmed-linked to her own account (PortalRequestTestCase).
        $this->pending(self::CLIENT, '2026-11-02 08:00:00');
        $this->pending(self::FAMILY, '2026-11-02 08:00:01');
    }

    public function testTheQueueListsTheRequestWithItsBadges(): void
    {
        $this->signIn();

        self::assertMatchesRegularExpression('#Demandes<span class="badge">1</span>#', $this->page('/portal-requests'));
        $rows = $this->rows('/portal-requests');

        self::assertCount(1, $rows);
        self::assertSame(
            ['id' => $this->accountId, 'kind' => 'client_link', 'short' => 'Rattachement', 'subject' => 'Nom différent', 'petName' => 'Rex', 'clientName' => 'Sophie Dupont · Jean DUPOND', 'flags' => ['Déjà relié à un autre compte Portal']],
            self::pick($rows[0], 'id', 'kind', 'short', 'subject', 'petName', 'clientName', 'flags'),
        );
        self::assertSame('/portal-requests/rattachements/' . $this->accountId . '/sheet', $rows[0]['sheet']);
        self::assertSame([$this->accountId], $this->ids('/portal-requests?statut=submitted&type=client_link'));
        self::assertSame([], $this->ids('/portal-requests?statut=done'));
        self::assertSame([], $this->ids('/portal-requests?type=pet_change'));
    }

    /** AC 267. */
    public function testTheDetailShowsTheProfileNameNextToEachFileWithItsBadges(): void
    {
        $this->signIn();

        $html = $this->page('/portal-requests/rattachements/' . $this->accountId);
        self::assertStringNotContainsString(self::XSS, $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt; Dupond', $html);

        $sheet = $this->sheet('/portal-requests/rattachements/' . $this->accountId);
        self::assertSame(self::XSS . ' Dupond', $sheet['ownerName']);
        self::assertSame([
            'confirm' => '/portal-requests/rattachements/' . $this->accountId . '/confirm',
            'reject'  => '/portal-requests/rattachements/' . $this->accountId . '/reject',
        ], $sheet['actions']);

        $files = array_map(self::arr(...), self::arr($sheet['files']));
        self::assertSame([self::CLIENT, self::FAMILY], array_column($files, 'clientId'));
        self::assertSame(['Sophie Dupont', ['Rex'], true, true], [$files[0]['name'], $files[0]['petNames'], $files[0]['mismatch'], $files[0]['linkedElsewhere']]);
        self::assertSame(['Jean DUPOND', false, false], [$files[1]['name'], $files[1]['mismatch'], $files[1]['linkedElsewhere']], 'same last name, accents and case aside');

        // The same sheet, as JSON for the page module.
        self::assertSame($sheet, $this->json('/portal-requests/rattachements/' . $this->accountId . '/sheet'));
    }

    /** AC 76: confirmed, the account reaches the files; a second answer gets « Demande déjà traitée. ». */
    public function testConfirmingLinksTheAccount(): void
    {
        $this->signIn();
        $token = $this->linkToken();

        $this->answer('confirm', $token);

        self::assertResponseRedirects('/portal-requests', 303);
        self::assertStringContainsString('Rattachement confirmé', $this->flashes());
        self::assertSame(['confirmed', 'confirmed'], $this->statuses());

        $this->answer('reject', $token);
        self::assertStringContainsString('Demande déjà traitée.', $this->flashes());
        self::assertSame(['confirmed', 'confirmed'], $this->statuses());
        $this->page('/portal-requests/rattachements/' . $this->accountId);
        self::assertResponseStatusCodeSame(404);
    }

    /** AC 76: rejected, the links are revoked. */
    public function testRejectingRevokesTheLinks(): void
    {
        $this->signIn();

        $this->answer('reject', $this->linkToken());

        self::assertResponseRedirects('/portal-requests', 303);
        self::assertStringContainsString('Rattachement refusé', $this->flashes());
        self::assertSame(['revoked', 'revoked'], $this->statuses());
    }

    public function testOnlyTheFilesShownAreAnsweredTheRestStaysPending(): void
    {
        $this->signIn();

        $this->answer('confirm', $this->linkToken(), [self::FAMILY]);

        self::assertResponseRedirects('/portal-requests/rattachements/' . $this->accountId, 303);
        self::assertSame(['pending', 'confirmed'], $this->statuses());
    }

    public function testTheTokenAndThePermissionAreChecked(): void
    {
        $this->signIn();

        $this->answer('confirm', 'forged');
        self::assertResponseStatusCodeSame(403);
        self::assertSame(['pending', 'pending'], $this->statuses());

        $token = $this->linkToken();
        $this->withoutPermissions(ClinicPermissionGuard::MANAGE_ANIMALS);
        $this->page('/portal-requests/rattachements/' . $this->accountId);
        self::assertResponseStatusCodeSame(403);
        $this->page('/portal-requests/rattachements/' . $this->accountId . '/sheet');
        self::assertResponseStatusCodeSame(403);
        $this->answer('reject', $token);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(['pending', 'pending'], $this->statuses());
    }

    public function testAnotherClinicsOrAnUnknownAccountIsNotFound(): void
    {
        $this->signIn();

        $this->page('/portal-requests/rattachements/' . Uuid::v7()->toRfc4122());
        self::assertResponseStatusCodeSame(404);
        $this->page('/portal-requests/rattachements/' . $this->ownerId);
        self::assertResponseStatusCodeSame(404, 'a confirmed account has nothing pending');
        $this->page('/portal-requests/rattachements/' . $this->ownerId . '/sheet');
        self::assertResponseStatusCodeSame(404);
    }

    /** S6-F3: the answer is recorded and listed under « Traitées » with who answered; its sheet says so. */
    public function testAConfirmedRequestIsListedUnderTraiteesWithWhoAnswered(): void
    {
        $this->signIn();
        $this->answer('confirm', $this->linkToken());

        $decisions = $this->connection()->fetchAllAssociative('SELECT BIN_TO_UUID(id) AS id, outcome, decided_by_name, client_ids FROM client_portal__link_decisions');
        self::assertCount(1, $decisions);
        ['id' => $id, 'decided_by_name' => $by] = $decisions[0];
        \assert(\is_string($id) && \is_string($by));
        \assert(\is_string($decisions[0]['client_ids']));
        self::assertSame(['confirmed', [self::CLIENT, self::FAMILY]], [$decisions[0]['outcome'], json_decode($decisions[0]['client_ids'], true)]);
        self::assertNotSame('', $by);

        self::assertSame([], $this->ids('/portal-requests'));
        $rows = $this->rows('/portal-requests?statut=done');
        self::assertCount(1, $rows);
        self::assertSame(
            ['id' => $id, 'kind' => 'client_link', 'status' => 'done', 'statusLabel' => 'Traitée', 'resolvedBy' => $by, 'clientName' => 'Sophie Dupont · Jean DUPOND', 'flags' => [], 'age' => null, 'sheet' => '/portal-requests/rattachements/decisions/' . $id . '/sheet'],
            self::pick($rows[0], 'id', 'kind', 'status', 'statusLabel', 'resolvedBy', 'clientName', 'flags', 'age', 'sheet'),
        );
        self::assertIsString($rows[0]['resolvedDay']);
        self::assertSame([$id], $this->ids('/portal-requests?statut=tout&type=client_link'));
        self::assertSame([], $this->ids('/portal-requests?statut=rejected'));

        $sheet = $this->sheet('/portal-requests/rattachements/decisions/' . $id);
        self::assertSame(
            ['type' => 'link', 'status' => 'done', 'decided' => true, 'actions' => null, 'resolvedBy' => $by, 'url' => '/portal-requests/rattachements/decisions/' . $id],
            self::pick($sheet, 'type', 'status', 'decided', 'actions', 'resolvedBy', 'url'),
        );
        self::assertIsString($sheet['resolvedAt']);
        self::assertSame([self::CLIENT, self::FAMILY], array_column(array_map(self::arr(...), self::arr($sheet['files'])), 'clientId'));
        // Opened without filters, the page lists its own state.
        self::assertSame('done', self::arr($this->payload('/portal-requests/rattachements/decisions/' . $id)['filters'])['statut']);
        self::assertSame($sheet, $this->json('/portal-requests/rattachements/decisions/' . $id . '/sheet'));
    }

    public function testARejectedRequestIsListedUnderRefusees(): void
    {
        $this->signIn();
        $this->answer('reject', $this->linkToken());

        $rows = $this->rows('/portal-requests?statut=rejected');
        self::assertCount(1, $rows);
        self::assertSame(['client_link', 'rejected', 'Refusée'], [$rows[0]['kind'], $rows[0]['status'], $rows[0]['statusLabel']]);
        self::assertIsString($rows[0]['resolvedBy']);
        \assert(\is_string($rows[0]['sheet']));
        self::assertSame('rejected', $this->json($rows[0]['sheet'])['status']);
    }

    public function testAnotherClinicsOrAnUnknownDecisionIsNotFound(): void
    {
        $this->signIn();
        $unknown = Uuid::v7()->toRfc4122();

        $this->page('/portal-requests/rattachements/decisions/' . $unknown);
        self::assertResponseStatusCodeSame(404);
        $this->page('/portal-requests/rattachements/decisions/' . $unknown . '/sheet');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheDashboardAsksToCheckTheRequest(): void
    {
        $this->signIn();

        $crawler = $this->browser->request('GET', self::HOST . '/dashboard');
        self::assertResponseIsSuccessful();

        $tasks = $crawler->filter('.tk')->reduce(static fn ($node): bool => str_contains((string) $node->filter('a.go')->attr('href'), '/portal-requests/rattachements/'));
        self::assertCount(1, $tasks);
        self::assertSame(['jour', 'asv'], [$tasks->attr('data-grp'), $tasks->attr('data-vis')]);
        self::assertStringContainsString('Demande de rattachement Portal — Sophie Dupont, Jean DUPOND', $tasks->text());
        self::assertStringContainsString('nom différent', $tasks->text());
    }

    private function pending(string $clientId, string $requestedAtUtc): void
    {
        $this->service(ClientLinkRepositoryInterface::class)->save(ClientLink::requestFromSuggestion(
            ClientLinkId::fromString(Uuid::v7()->toRfc4122()),
            $this->accountId,
            self::CLINIC,
            $clientId,
            new \DateTimeImmutable($requestedAtUtc, new \DateTimeZone('UTC')),
        ));
    }

    private function linkToken(): string
    {
        $token = self::arr($this->payload('/portal-requests/rattachements/' . $this->accountId)['tokens'])['link'];
        \assert(\is_string($token));

        return $token;
    }

    /**
     * @param ?list<string> $clientIds
     */
    private function answer(string $action, string $token, ?array $clientIds = null): void
    {
        $this->browser->request('POST', self::HOST . '/portal-requests/rattachements/' . $this->accountId . '/' . $action, [
            '_token'     => $token,
            'client_ids' => $clientIds ?? [self::CLIENT, self::FAMILY],
        ]);
    }

    /**
     * @return list<string> the account's link statuses: Sophie Dupont's file, then Jean Dupond's
     */
    private function statuses(): array
    {
        return array_map(function (string $clientId): string {
            $status = $this->connection()->fetchOne(
                'SELECT status FROM client_portal__client_links WHERE user_id = UUID_TO_BIN(?) AND client_id = UUID_TO_BIN(?)',
                [$this->accountId, $clientId],
            );
            self::assertIsString($status);

            return $status;
        }, [self::CLIENT, self::FAMILY]);
    }
}
