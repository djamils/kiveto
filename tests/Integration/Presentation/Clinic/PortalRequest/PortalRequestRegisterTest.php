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
 * The « Demandes » register as the page module reads it without reloading:
 * clinic_portal_requests_search (status segments, type, search, pages and
 * page sizes, counters), the sheets as JSON, and the answers posted in the
 * background (`{success, message}`), next to the PRG of a plain form.
 */
final class PortalRequestRegisterTest extends PortalRequestTestCase
{
    public function testTheSearchPagesTheRegisterAndCountsTheWholeQueue(): void
    {
        $ids = [];
        foreach (range(1, 26) as $n) {
            $ids[] = $this->change('name', 'Nom ' . $n, petId: $this->pet(\sprintf('Chat %02d', $n)));
        }
        $this->signIn();

        $first = $this->json('/portal-requests/search');
        self::assertSame([26, 1, 25], [$first['total'], $first['page'], $first['pp']]);
        self::assertCount(25, self::rowsOf($first));
        // The oldest first; same instant, then by id.
        $order = array_column(self::rowsOf($first), 'id');

        $second = $this->json('/portal-requests/search?page=2');
        self::assertSame([26, 2], [$second['total'], $second['page']]);
        self::assertCount(1, self::rowsOf($second));
        self::assertNotContains(self::rowsOf($second)[0]['id'], $order);
        self::assertEqualsCanonicalizing($ids, [...$order, self::rowsOf($second)[0]['id']]);

        // A page beyond the last shows the last; 50 a page holds them all; an odd size is 25.
        self::assertSame(2, $this->json('/portal-requests/search?page=9')['page']);
        self::assertCount(26, self::rowsOf($this->json('/portal-requests/search?pp=50')));
        self::assertSame(25, $this->json('/portal-requests/search?pp=7')['pp']);

        // The counters follow the queue, not the page.
        self::assertSame(['submitted' => 26, 'done' => null, 'rejected' => null, 'tout' => null], $second['counts']);
        self::assertSame(['submitted' => 26, 'done' => 0, 'rejected' => 0, 'tout' => 26], $this->json('/portal-requests/search?statut=tout')['counts']);
        self::assertSame(26, self::arr($second['head'])['pending']);
        self::assertCount(4, self::arr(self::arr($second['head'])['next']));
    }

    public function testTheSearchReadsTheClientTheAnimalTheSubjectAndTheNote(): void
    {
        $chip    = $this->change('chip_number', '250269801234567', 'Posée chez l’éleveur');
        $nala    = $this->change('name', 'Nala II', petId: $this->pet('Nâla'));
        $renewal = $this->renewal();
        $this->signIn();

        self::assertSame([$chip], $this->searched('puce'), 'the field asked');
        self::assertSame([$chip], $this->searched('ELEVEUR'), 'the note, accents and case aside');
        self::assertSame([$nala], $this->searched('nala'), 'the animal');
        self::assertSame([$renewal], $this->searched('meloxidyl'), 'the medicine of a renewal');
        self::assertCount(3, $this->searched('dupont'), 'the client');
        self::assertSame([], $this->searched('introuvable'));

        $found = $this->json('/portal-requests/search?q=puce');
        self::assertSame(1, $found['total']);
        self::assertSame(3, self::arr($found['counts'])['submitted'], 'the search narrows the rows, not the counters');
    }

    public function testTheStatusAndTypeFiltersAndTheHistory(): void
    {
        $done     = $this->change('name', 'Rex II');
        $rejected = $this->change('weight', '12', petId: $this->pet('Nala'));
        $waiting  = $this->renewal('improved');
        $this->signIn();
        $this->resolve($done, ['outcome' => 'done', 'message' => 'C’est fait.']);
        $this->resolve($rejected, ['outcome' => 'rejected']);

        self::assertSame([$waiting], $this->searchedIds('statut=submitted'));
        self::assertSame([$done], $this->searchedIds('statut=done'));
        self::assertSame([$rejected], $this->searchedIds('statut=rejected'));
        // « Toutes »: the waiting ones first, then the answered ones newest first.
        self::assertSame([$waiting, $rejected, $done], $this->searchedIds('statut=tout'));
        self::assertSame([$rejected, $done], $this->searchedIds('statut=tout&type=pet_change'));
        // An unknown status is « À traiter », an unknown type is every type.
        self::assertSame([$waiting], $this->searchedIds('statut=closed&type=vaccine'));

        $register = $this->json('/portal-requests/search?statut=done');
        self::assertSame(['submitted' => 1, 'done' => 1, 'rejected' => 1, 'tout' => 3], $register['counts']);
        $row = self::rowsOf($register)[0];
        self::assertSame(['done', 'Traitée', 's-clot', null, false], [$row['status'], $row['statusLabel'], $row['statusClass'], $row['age'], $row['late']]);
        self::assertIsString($row['resolvedBy']);

        // A request opened from the bell shows the list of its own state.
        $payload = $this->payload('/portal-requests/' . $done);
        self::assertSame('done', self::arr($payload['filters'])['statut']);
        self::assertSame([$done], array_column(self::rowsOf(self::arr($payload['register'])), 'id'));
        self::assertSame('submitted', self::arr($this->payload('/portal-requests/' . $done . '?statut=submitted')['filters'])['statut']);
        self::assertSame('tout', self::arr($this->payload('/portal-requests?statut=')['filters'])['statut'], 'the former « Toutes » link');
    }

    public function testAClinicThatNeverReceivedARequestIsBlank(): void
    {
        $this->signIn();

        $head = self::arr($this->json('/portal-requests/search')['head']);

        self::assertSame([0, true, null], [$head['pending'], $head['blank'], self::arr($head['first'])['submitted']]);
    }

    public function testTheSheetsAreJson(): void
    {
        $change = $this->change('name', 'Rex II', self::XSS);
        $this->signIn();

        $sheet = $this->json('/portal-requests/' . $change . '/sheet');

        self::assertSame(['change', $change, 'submitted', 'À traiter', self::XSS], [$sheet['type'], $sheet['id'], $sheet['status'], $sheet['statusLabel'], $sheet['note']]);
        self::assertSame(['/portal-requests/' . $change, '/portal-requests/' . $change . '/sheet'], [$sheet['url'], $sheet['sheet']]);
        self::assertSame('Modification de fiche · Nom', $sheet['title']);
        self::assertTrue($sheet['canResolve']);
        self::assertSame($sheet, $this->sheet('/portal-requests/' . $change));
    }

    public function testAnAnswerPostedInTheBackgroundIsJson(): void
    {
        $id    = $this->change('name', 'Rex II');
        $other = $this->change('weight', '12', petId: $this->pet('Nala'));
        $this->signIn();
        $token = $this->token($id);

        $this->post('/portal-requests/' . $id . '/resolve', ['_token' => $token, 'outcome' => 'done', 'message' => 'Fait.']);
        self::assertResponseIsSuccessful();
        self::assertSame(['success' => true, 'message' => 'Demande traitée — le client est prévenu.'], $this->answered());

        // A second answer loses, with who answered first.
        $this->post('/portal-requests/' . $id . '/resolve', ['_token' => $token, 'outcome' => 'rejected']);
        self::assertResponseStatusCodeSame(409);
        $answer = $this->answered();
        self::assertFalse($answer['success']);
        self::assertIsString($answer['message']);
        self::assertMatchesRegularExpression('/Demande déjà traitée par .+\./u', $answer['message']);

        $this->post('/portal-requests/' . $other . '/resolve', ['_token' => $token, 'outcome' => 'closed']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['success' => false, 'message' => 'Choisissez « Traiter » ou « Refuser ».'], $this->answered());

        $this->post('/portal-requests/' . $other . '/resolve', ['_token' => $token, 'outcome' => 'rejected', 'message' => str_repeat('a', 281)]);
        self::assertResponseStatusCodeSame(422);

        // The register follows.
        $register = $this->json('/portal-requests/search?statut=tout');
        self::assertSame(['submitted' => 1, 'done' => 1, 'rejected' => 0, 'tout' => 2], $register['counts']);
    }

    public function testARenewalAnsweredWithoutCreatePrescriptionIsForbiddenInJson(): void
    {
        $renewal = $this->renewal();
        $this->withoutPermissions(ClinicPermissionGuard::CREATE_PRESCRIPTION);
        $this->signIn();

        $this->post('/portal-requests/' . $renewal . '/resolve', ['_token' => $this->token($renewal), 'outcome' => 'done']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['success' => false, 'message' => 'Seul un membre habilité à prescrire peut répondre à une demande de renouvellement.'], $this->answered());
    }

    public function testARattachementAnsweredInTheBackgroundTellsWhatStillWaits(): void
    {
        $family  = '01960000-0000-7000-8000-0000000d6d02';
        $account = PortalUserFactory::new()->withPhone('+33612349045')->create()->getId()->toRfc4122();
        ClientEntityFactory::new()->withId($family)->withClinicId(self::CLINIC)->withName('Jean', 'Dupont')->active()->create();
        $this->service(OwnerProfileRepositoryInterface::class)->save(OwnerProfile::createFromClient($account, 'Jean', 'Dupont', null, 'v1', new \DateTimeImmutable('2026-11-01')));
        foreach ([self::CLIENT, $family] as $clientId) {
            $this->service(ClientLinkRepositoryInterface::class)->save(ClientLink::requestFromSuggestion(ClientLinkId::fromString(Uuid::v7()->toRfc4122()), $account, self::CLINIC, $clientId, new \DateTimeImmutable('2026-11-02 08:00:00', new \DateTimeZone('UTC'))));
        }
        $this->signIn();
        $token = self::arr($this->payload('/portal-requests/rattachements/' . $account)['tokens'])['link'];
        \assert(\is_string($token));

        $this->post('/portal-requests/rattachements/' . $account . '/confirm', ['_token' => $token, 'client_ids' => [$family]]);
        self::assertResponseIsSuccessful();
        self::assertSame(['success' => true, 'message' => 'Rattachement confirmé — le compte Portal accède désormais au dossier.', 'pending' => true, 'decision' => null], $this->answered());

        $this->post('/portal-requests/rattachements/' . $account . '/reject', ['_token' => $token, 'client_ids' => [self::CLIENT]]);
        $answer   = $this->answered();
        $decision = self::arr($answer['decision']);
        self::assertSame(['success' => true, 'message' => 'Rattachement refusé — la clinique ne sera plus proposée à ce compte Portal.', 'pending' => false], \array_slice($answer, 0, 3, true));
        // REG-02: the sheet moves to the recorded rejection, which a reload opens
        self::assertIsString($decision['id']);
        self::assertSame(['/portal-requests/rattachements/decisions/' . $decision['id'], '/portal-requests/rattachements/decisions/' . $decision['id'] . '/sheet'], [$decision['url'], $decision['sheet']]);
        $sheet = $this->sheet('/portal-requests/rattachements/decisions/' . $decision['id']);
        self::assertSame(['rejected', $decision['id']], [$sheet['status'], $sheet['id']]);

        $this->post('/portal-requests/rattachements/' . $account . '/reject', ['_token' => $token, 'client_ids' => [self::CLIENT]]);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(['success' => false, 'message' => 'Demande déjà traitée.', 'pending' => false, 'decision' => null], $this->answered());
        self::assertSame([], $this->searchedIds('type=client_link'));
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function post(string $path, array $fields): void
    {
        $this->browser->request('POST', self::HOST . $path, $fields, server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function answered(): array
    {
        return self::arr(json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<mixed>
     */
    private function searched(string $q): array
    {
        return $this->searchedIds('statut=tout&q=' . rawurlencode($q));
    }

    /**
     * @return list<mixed>
     */
    private function searchedIds(string $query): array
    {
        return array_column(self::rowsOf($this->json('/portal-requests/search?' . $query)), 'id');
    }
}
