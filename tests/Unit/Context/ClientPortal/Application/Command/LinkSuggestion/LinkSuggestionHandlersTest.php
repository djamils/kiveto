<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\LinkSuggestion;

use App\Context\ClientPortal\Application\Command\AcceptLinkSuggestion\AcceptLinkSuggestion;
use App\Context\ClientPortal\Application\Command\AcceptLinkSuggestion\AcceptLinkSuggestionHandler;
use App\Context\ClientPortal\Application\Command\ConfirmClientLink\ConfirmClientLink;
use App\Context\ClientPortal\Application\Command\ConfirmClientLink\ConfirmClientLinkHandler;
use App\Context\ClientPortal\Application\Command\RejectClientLink\RejectClientLink;
use App\Context\ClientPortal\Application\Command\RejectClientLink\RejectClientLinkHandler;
use App\Context\ClientPortal\Application\Port\ClinicLabel;
use App\Context\ClientPortal\Application\Port\MobileFileMatch;
use App\Context\ClientPortal\Application\Port\OwnerAccount;
use App\Context\ClientPortal\Application\Query\ListLinkSuggestions\LinkSuggestion;
use App\Context\ClientPortal\Application\Query\ListLinkSuggestions\ListLinkSuggestions;
use App\Context\ClientPortal\Application\Query\ListLinkSuggestions\ListLinkSuggestionsHandler;
use App\Context\ClientPortal\Application\Query\ListOwnerClinics\ListOwnerClinics;
use App\Context\ClientPortal\Application\Query\ListOwnerClinics\ListOwnerClinicsHandler;
use App\Context\ClientPortal\Application\Query\ListOwnerClinics\OwnerClinic;
use App\Context\ClientPortal\Application\Service\LinkDecisionRecorder;
use App\Context\ClientPortal\Application\Service\LinkSuggestions;
use App\Context\ClientPortal\Domain\Event\ClientLinkConfirmed;
use App\Context\ClientPortal\Domain\Event\ClientLinkRevoked;
use App\Context\ClientPortal\Domain\Exception\LinkSuggestionNotFoundException;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use App\Context\ClientPortal\Domain\Link\LinkDecision;
use App\Tests\Unit\Context\ClientPortal\Support\ClientPortalWorld as W;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryLinkDecisions;
use App\Tests\Unit\Context\ClientPortal\Support\LinkFakes;
use App\Tests\Unit\Context\ClientPortal\Support\RecordingPortalLock;
use PHPUnit\Framework\TestCase;

/**
 * S7b link suggestions (AC 75, 76, 88): computed per clinic from the
 * account's mobile, accepted into pending links, then confirmed or rejected
 * by the clinic; a rejected suggestion never comes back.
 */
final class LinkSuggestionHandlersTest extends TestCase
{
    private const string USER     = '01960000-0000-7000-8000-0000000c0001';
    private const string OTHER    = '01960000-0000-7000-8000-0000000c0002';
    private const string CLINIC_C = '01960000-0000-7000-8000-00000000c003';
    private const string CLIENT3  = '01960000-0000-7000-8000-00000000d003';
    private const string CLIENT4  = '01960000-0000-7000-8000-00000000d004';

    private W $world;

    private LinkFakes $fakes;

    private InMemoryLinkDecisions $decisions;

    protected function setUp(): void
    {
        $this->world                                 = new W();
        $this->fakes                                 = new LinkFakes();
        $this->decisions                             = new InMemoryLinkDecisions();
        $this->fakes->accounts[self::USER]           = new OwnerAccount(self::USER, W::PHONE, null, false);
        $this->world->fakes->clinics[W::CLINIC]      = new ClinicLabel('Clinique des Lilas', 'Lyon', 'Europe/Paris');
        $this->world->fakes->clinics[W::CLINIC2]     = new ClinicLabel('Clinique du Parc', null);
        $this->world->fakes->clinics[self::CLINIC_C] = new ClinicLabel('Animalerie Centrale', 'Paris');
        $this->world->fakes->mobileMatches[W::PHONE] = [
            new MobileFileMatch(W::CLINIC, W::CLIENT, 'Sophie', 'Martin', ['Moka']),
            new MobileFileMatch(W::CLINIC, W::CLIENT2, 'Paul', 'Martin', []),
            new MobileFileMatch(W::CLINIC2, self::CLIENT3, 'Sophie', 'Martin', ['Rex']),
            new MobileFileMatch(self::CLINIC_C, self::CLIENT4, 'Sophie', 'Martin', []),
        ];
    }

    /** AC 75: one suggestion per clinic, by clinic name, with its name and city only. */
    public function testOneSuggestionPerClinicWithItsNameAndCityOnly(): void
    {
        // Every matching file of clinic C is already linked (here revoked): C is not suggested.
        $this->link(self::CLINIC_C, self::CLIENT4, ClientLinkStatus::REVOKED);

        $suggestions = $this->suggestions();

        self::assertSame([
            ['s:' . self::USER . ':' . W::CLINIC, 'Clinique des Lilas', 'Lyon', 'phone'],
            ['s:' . self::USER . ':' . W::CLINIC2, 'Clinique du Parc', null, 'phone'],
        ], array_map(static fn (LinkSuggestion $s): array => [$s->id, $s->clinicName, $s->city, $s->matchedOn], $suggestions));
    }

    public function testAClinicStaysSuggestedWhileOneOfItsFilesIsNotLinked(): void
    {
        $this->link(W::CLINIC, W::CLIENT, ClientLinkStatus::CONFIRMED);
        // Another account's confirmed link to the file does not hide it (families share files).
        $this->link(W::CLINIC2, self::CLIENT3, ClientLinkStatus::CONFIRMED, self::OTHER);

        self::assertSame(['Animalerie Centrale', 'Clinique des Lilas', 'Clinique du Parc'], array_map(static fn (LinkSuggestion $s): string => $s->clinicName, $this->suggestions()));
    }

    public function testAnAccountThatIsNotActiveGetsNoSuggestion(): void
    {
        $this->fakes->accounts = [];

        self::assertSame([], $this->suggestions());
    }

    /** AC 76: accepting opens one pending link per matching family file, then rings the animal managers. */
    public function testAcceptingOpensOnePendingLinkPerMatchingFileOfTheClinic(): void
    {
        $status = $this->accept('s:' . self::USER . ':' . W::CLINIC);

        self::assertSame('pending', $status);
        $pending = $this->world->links->findPendingByClinic(W::CLINIC);
        self::assertSame([W::CLIENT, W::CLIENT2], array_map(static fn (ClientLink $l): string => $l->clientId(), $pending));
        self::assertSame([null, '2026-10-29 09:00'], [$pending[0]->linkedAt(), $pending[0]->requestedAt()?->format('Y-m-d H:i')]);
        self::assertFalse($this->world->links->hasConfirmed(self::USER, W::CLINIC));
        self::assertSame([[
            'clinicId'    => W::CLINIC,
            'userIds'     => ['asv-1'],
            'ownerUserId' => self::USER,
            'key'         => 'portal-link-request:' . $pending[0]->id()->toString(),
            'title'       => 'Demande de rattachement Portal',
            'detail'      => 'Sophie Martin · Paul Martin',
        ]], $this->fakes->bells);

        // The clinic is pending: no longer suggested; GET /me lists it pending.
        self::assertSame(['Animalerie Centrale', 'Clinique du Parc'], array_map(static fn (LinkSuggestion $s): string => $s->clinicName, $this->suggestions()));
        $clinics = $this->ownerClinics();
        self::assertSame([[W::CLINIC, OwnerClinic::PENDING, null, []]], array_map(static fn (OwnerClinic $c): array => [$c->clinicId, $c->linkStatus, $c->linkedAt, $c->petNames], $clinics));
    }

    public function testReplayingAnAcceptanceAnswersPendingWithoutWriting(): void
    {
        $this->accept('s:' . self::USER . ':' . W::CLINIC);
        $saves = $this->world->links->saves;

        self::assertSame('pending', $this->accept('s:' . self::USER . ':' . W::CLINIC));
        self::assertSame($saves, $this->world->links->saves);
        self::assertCount(1, $this->fakes->bells);
    }

    public function testAFileLinkedMeanwhileIsNeitherDuplicatedNorAnnounced(): void
    {
        $this->world->fakes->mobileMatches[W::PHONE] = [new MobileFileMatch(W::CLINIC2, self::CLIENT3, 'Sophie', 'Martin', [])];
        // The suggestion was read before an invitation linked the account to the file.
        $blind = new readonly class($this->world->links) implements ClientLinkRepositoryInterface {
            public function __construct(private ClientLinkRepositoryInterface $links)
            {
            }

            public function save(ClientLink $link): void
            {
                $this->links->save($link);
            }

            public function findForUpdate(string $userId, string $clinicId, string $clientId): ?ClientLink
            {
                return $this->links->findForUpdate($userId, $clinicId, $clientId);
            }

            public function findByClient(string $clinicId, string $clientId): array
            {
                return $this->links->findByClient($clinicId, $clientId);
            }

            public function findConfirmedByUser(string $userId): array
            {
                return [];
            }

            public function findActiveByUser(string $userId): array
            {
                return [];
            }

            public function findPendingByClinic(string $clinicId): array
            {
                return [];
            }

            public function findByUser(string $userId): array
            {
                return [];
            }

            public function hasConfirmed(string $userId, string $clinicId): bool
            {
                return false;
            }
        };
        $this->link(W::CLINIC2, self::CLIENT3, ClientLinkStatus::CONFIRMED);
        $saves   = $this->world->links->saves;
        $handler = new AcceptLinkSuggestionHandler(new LinkSuggestions($this->fakes, $this->world->fakes, $blind, $this->world->fakes, $this->fakes), $blind, $this->fakes, $this->fakes, $this->fakes, $this->world->fakes, $this->world->clock, new RecordingPortalLock());

        self::assertSame('pending', $handler(new AcceptLinkSuggestion(self::USER, 's:' . self::USER . ':' . W::CLINIC2)));
        self::assertSame($saves, $this->world->links->saves);
        self::assertSame([], $this->fakes->bells);
    }

    public function testAnUnknownOrForeignSuggestionIsNotFound(): void
    {
        foreach (['s:' . self::OTHER . ':' . W::CLINIC, 'forged', 's:' . self::USER . ':' . self::CLINIC_C . 'x'] as $id) {
            try {
                $this->accept($id);
                self::fail($id . ' was accepted');
            } catch (LinkSuggestionNotFoundException $exception) {
                self::assertSame('Link suggestion not found.', $exception->getMessage());
            }
        }

        self::assertSame([], $this->world->links->findByUser(self::USER));
    }

    /** AC 76: on confirmation the links become confirmed and the clinic opens. */
    public function testTheClinicConfirmsTheFilesItWasShown(): void
    {
        $this->accept('s:' . self::USER . ':' . W::CLINIC);
        $this->world->clock->now = new \DateTimeImmutable('2026-10-30 08:00:00', new \DateTimeZone('UTC'));

        $confirmed = $this->confirmHandler()(new ConfirmClientLink(W::CLINIC, self::USER, [W::CLIENT, W::CLIENT, 'unknown'], W::ACTOR, 'Dr Lemoine'));

        self::assertSame(1, $confirmed, 'only the files listed');
        self::assertTrue($this->world->links->hasConfirmed(self::USER, W::CLINIC));
        self::assertSame(ClientLinkStatus::PENDING, $this->world->links->findForUpdate(self::USER, W::CLINIC, W::CLIENT2)?->status());
        self::assertCount(1, array_filter($this->world->fakes->events, static fn (object $e): bool => $e instanceof ClientLinkConfirmed));

        // Confirmed wins over pending in GET /me, its animals shown.
        $this->world->fakes->pets = [W::CLINIC . '/' . W::CLIENT => ['Moka'], W::CLINIC . '/' . W::CLIENT2 => ['Rex']];
        self::assertSame([[W::CLINIC, OwnerClinic::CONFIRMED, '2026-10-30', ['Moka']]], array_map(static fn (OwnerClinic $c): array => [$c->clinicId, $c->linkStatus, $c->linkedAt?->format('Y-m-d'), $c->petNames], $this->ownerClinics()));

        // S6-F3: the answer is recorded once, with the files it confirmed.
        self::assertSame([[W::CLINIC, self::USER, 'confirmed', [W::CLIENT], '2026-10-30 08:00', W::ACTOR, 'Dr Lemoine']], $this->decisions());

        // A second confirmation of the same file changes nothing.
        self::assertSame(0, $this->confirmHandler()(new ConfirmClientLink(W::CLINIC, self::USER, [W::CLIENT], W::ACTOR, 'Dr Lemoine')));
        self::assertCount(1, $this->decisions->decisions, 'nothing decided, nothing recorded');
    }

    /** AC 76, 88: on rejection the links are revoked, consents untouched, and the suggestion never comes back. */
    public function testTheClinicRejectsAndTheSuggestionNeverComesBack(): void
    {
        $this->accept('s:' . self::USER . ':' . W::CLINIC);

        $rejected = $this->rejectHandler()(new RejectClientLink(W::CLINIC, self::USER, [W::CLIENT, W::CLIENT2, 'unknown'], W::ACTOR, 'Dr Lemoine'));

        self::assertSame(2, $rejected);
        self::assertSame([], $this->world->links->findPendingByClinic(W::CLINIC));
        self::assertFalse($this->world->links->hasConfirmed(self::USER, W::CLINIC));
        $revoked = array_values(array_filter($this->world->fakes->events, static fn (object $e): bool => $e instanceof ClientLinkRevoked));
        self::assertSame(['link_rejected', 'link_rejected'], array_map(static fn (ClientLinkRevoked $e): string => $e->reason, $revoked));
        self::assertSame([], $this->ownerClinics());
        self::assertNotContains('Clinique des Lilas', array_map(static fn (LinkSuggestion $s): string => $s->clinicName, $this->suggestions()));

        self::assertSame([[W::CLINIC, self::USER, 'rejected', [W::CLIENT, W::CLIENT2], '2026-10-29 09:00', W::ACTOR, 'Dr Lemoine']], $this->decisions());

        // Rejecting again (a colleague's late click) changes nothing.
        self::assertSame(0, $this->rejectHandler()(new RejectClientLink(W::CLINIC, self::USER, [W::CLIENT], W::ACTOR, 'Dr Lemoine')));
        self::assertCount(1, $this->decisions->decisions);
    }

    private function confirmHandler(): ConfirmClientLinkHandler
    {
        return new ConfirmClientLinkHandler($this->world->links, new LinkDecisionRecorder($this->decisions, $this->world->fakes), $this->world->clock, $this->world->publisher());
    }

    private function rejectHandler(): RejectClientLinkHandler
    {
        return new RejectClientLinkHandler($this->world->links, new LinkDecisionRecorder($this->decisions, $this->world->fakes), $this->world->clock, $this->world->publisher());
    }

    /**
     * @return list<array{string, string, string, list<string>, string, string, string}>
     */
    private function decisions(): array
    {
        return array_values(array_map(static fn (LinkDecision $d): array => [
            $d->clinicId(), $d->userId(), $d->outcome()->value, $d->clientIds(), $d->decidedAt()->format('Y-m-d H:i'), $d->decidedByUserId(), $d->decidedByName(),
        ], $this->decisions->decisions));
    }

    private function link(string $clinicId, string $clientId, ClientLinkStatus $status, string $userId = self::USER): void
    {
        $this->world->links->save(ClientLink::reconstitute(
            ClientLinkId::fromString(\sprintf('01960000-0000-7000-8000-0000000001%02d', \count($this->world->links->links))),
            $userId,
            $clinicId,
            $clientId,
            $status,
            ClientLinkStatus::CONFIRMED === $status ? new \DateTimeImmutable('2026-10-01') : null,
            ClientLinkStatus::REVOKED === $status ? new \DateTimeImmutable('2026-10-02') : null,
        ));
    }

    /**
     * @return list<LinkSuggestion>
     */
    private function suggestions(): array
    {
        return new ListLinkSuggestionsHandler($this->service())(new ListLinkSuggestions(self::USER));
    }

    private function accept(string $id): string
    {
        $locks  = new RecordingPortalLock();
        $status = new AcceptLinkSuggestionHandler($this->service(), $this->world->links, $this->fakes, $this->fakes, $this->fakes, $this->world->fakes, $this->world->clock, $locks)(new AcceptLinkSuggestion(self::USER, $id));
        // Serialised per account: a double accept never inserts the same links twice
        self::assertSame(['account:' . self::USER], $locks->keys);

        return $status;
    }

    private function service(): LinkSuggestions
    {
        return new LinkSuggestions($this->fakes, $this->world->fakes, $this->world->links, $this->world->fakes, $this->fakes);
    }

    /**
     * @return list<OwnerClinic>
     */
    private function ownerClinics(): array
    {
        return new ListOwnerClinicsHandler($this->world->links, $this->world->fakes, $this->world->fakes)(new ListOwnerClinics(self::USER));
    }
}
