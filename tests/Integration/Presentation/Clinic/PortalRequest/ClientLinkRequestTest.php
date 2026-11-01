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

        $html = $this->page('/portal-requests');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('/portal-requests/rattachements/' . $this->accountId, $html);
        self::assertStringContainsString('Rattachement', $html);
        self::assertStringContainsString('Sophie Dupont · Jean DUPOND', $html);
        self::assertStringContainsString('Rex', $html);
        self::assertStringContainsString('Nom différent', $html);
        self::assertStringContainsString('Déjà relié à un autre compte Portal', $html);
        self::assertMatchesRegularExpression('#Demandes Portal<span class="badge">1</span>#', $html);

        $links = $this->page('/portal-requests?statut=submitted&type=client_link');
        self::assertStringContainsString('/portal-requests/rattachements/' . $this->accountId, $links);
        self::assertStringNotContainsString('/portal-requests/rattachements/', $this->page('/portal-requests?statut=done'));
        self::assertStringNotContainsString('/portal-requests/rattachements/', $this->page('/portal-requests?type=pet_change'));
    }

    /** AC 267. */
    public function testTheDetailShowsTheProfileNameNextToEachFileWithItsBadges(): void
    {
        $this->signIn();

        $crawler = $this->browser->request('GET', self::HOST . '/portal-requests/rattachements/' . $this->accountId);

        self::assertResponseIsSuccessful();
        $html = (string) $this->browser->getResponse()->getContent();
        self::assertStringNotContainsString(self::XSS, $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt; Dupond', $html);

        $rows = $crawler->filter('.prq-files tbody tr');
        self::assertSame([self::CLIENT, self::FAMILY], $rows->each(static fn ($row): string => (string) $row->attr('data-client-id')));
        self::assertStringContainsString('Sophie Dupont', $rows->eq(0)->text());
        self::assertStringContainsString('Rex', $rows->eq(0)->text());
        self::assertSame(['mismatch', 'linked-elsewhere'], $rows->eq(0)->filter('[data-badge]')->each(static fn ($b): string => (string) $b->attr('data-badge')));
        self::assertStringContainsString('Jean DUPOND', $rows->eq(1)->text());
        self::assertSame([], $rows->eq(1)->filter('[data-badge]')->each(static fn ($b): string => (string) $b->attr('data-badge')), 'same last name, accents and case aside');
        self::assertSame([self::CLIENT, self::FAMILY], $crawler->filter('#prqLinkConfirm input[name="client_ids[]"]')->each(static fn ($i): string => (string) $i->attr('value')));
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
        $crawler = $this->browser->request('GET', self::HOST . '/portal-requests/rattachements/' . $this->accountId);
        self::assertResponseIsSuccessful();

        return (string) $crawler->filter('#prqLinkConfirm input[name="_token"]')->attr('value');
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
