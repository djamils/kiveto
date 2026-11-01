<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Application\Command\CreateClient\ContactMethodDto;
use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Context\Client\Application\Command\SetClientConsent\SetClientConsent;
use App\Context\Client\Application\Command\UpdateClientCommunicationPreferences\UpdateClientCommunicationPreferences;
use App\Context\Client\Application\Query\GetClientConsents\ClientConsentsView;
use App\Context\Client\Application\Query\GetClientConsents\GetClientConsents;
use App\Context\Client\Application\Query\ListClientConsentHistory\ConsentHistoryItemView;
use App\Context\Client\Application\Query\ListClientConsentHistory\ListClientConsentHistory;
use App\Context\Client\Domain\Exception\ClientNotFoundException;
use App\Context\Client\Domain\Exception\StaleConsentsException;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\Repository\ConsentHistoryRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ConsentActor;
use App\Context\Client\Domain\ValueObject\ConsentActorType;
use App\Context\Client\Domain\ValueObject\ConsentChange;
use App\Context\Client\Domain\ValueObject\ConsentChangeAction;
use App\Context\Client\Domain\ValueObject\ConsentHistoryEntry;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use App\Context\Client\Domain\ValueObject\ConsentSource;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Pagination\CursorPage;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * S5b: every writer appends its history rows in the transaction of the
 * consents; the Portal reads the consents and the paged history back.
 */
final class DbalConsentHistoryRepositoryTest extends KernelTestCase
{
    private const string CLINIC_ID       = '0192a000-0000-7000-8000-0000000c0ac1';
    private const string OTHER_CLINIC_ID = '0192a000-0000-7000-8000-0000000c0ac2';
    private const string STAFF_ID        = '0192a000-0000-7000-8000-0000000c0aa1';
    private const string PORTAL_USER_ID  = '0192a000-0000-7000-8000-0000000c0aa2';

    /** AC 54, AC 55, AC 203 */
    public function testEveryWriterAppendsItsRowsAndThePortalReadsThemBack(): void
    {
        $clientId = $this->create(self::CLINIC_ID, ['marketing' => ['sms', 'email'], 'billing' => ['email']]);

        $this->commands()->dispatch(new SetClientConsent(self::CLINIC_ID, $clientId, 'marketing', ['sms'], 'portal', 'portal_user', self::PORTAL_USER_ID, 'Hélène Lambert', 'v1'));
        $version = $this->clients()->get(ClinicId::fromString(self::CLINIC_ID), ClientId::fromString($clientId))->version();
        $this->commands()->dispatch(new UpdateClientCommunicationPreferences(self::CLINIC_ID, $clientId, 'email', ['marketing' => ['sms'], 'billing' => ['sms']], 'clinic', 'staff', self::STAFF_ID, 'Julie Martin', $version));

        $page = $this->queries()->ask(new ListClientConsentHistory(self::CLINIC_ID, [$clientId]));
        self::assertInstanceOf(CursorPage::class, $page);
        self::assertNull($page->nextCursor);
        // Newest first: the rows of one save come in the reverse order of the diff.
        self::assertSame([
            ['billing', 'email', 'withdrawn', 'clinic', 'staff', self::STAFF_ID, 'Julie Martin', null],
            ['billing', 'sms', 'granted', 'clinic', 'staff', self::STAFF_ID, 'Julie Martin', null],
            ['marketing', 'email', 'withdrawn', 'portal', 'portal_user', self::PORTAL_USER_ID, 'Hélène Lambert', 'v1'],
            ['marketing', 'email', 'granted', 'clinic', 'staff', self::STAFF_ID, 'Julie Martin', null],
            ['marketing', 'sms', 'granted', 'clinic', 'staff', self::STAFF_ID, 'Julie Martin', null],
            ['billing', 'email', 'granted', 'clinic', 'staff', self::STAFF_ID, 'Julie Martin', null],
        ], array_map(self::describe(...), $this->items($page)));

        $consents = $this->queries()->ask(new GetClientConsents(self::CLINIC_ID, [$clientId]));
        self::assertIsArray($consents);
        $view = $consents[$clientId] ?? null;
        self::assertInstanceOf(ClientConsentsView::class, $view);
        self::assertSame('email', $view->preferredChannel);
        self::assertFalse($view->archived);
        self::assertSame(['billing', 'marketing'], array_keys($view->consents));
        self::assertSame(['sms'], $view->consentFor('marketing')?->channels);
        self::assertSame(['sms'], $view->consentFor('billing')?->channels);
    }

    /** AC 202: a sheet rendered before a Portal change cannot overwrite it. */
    public function testAStaleSheetDoesNotOverwriteAPortalWithdrawal(): void
    {
        $clientId = $this->create(self::CLINIC_ID, ['marketing' => ['sms']]);
        $rendered = $this->clients()->get(ClinicId::fromString(self::CLINIC_ID), ClientId::fromString($clientId))->version();

        $this->commands()->dispatch(new SetClientConsent(self::CLINIC_ID, $clientId, 'marketing', [], 'portal', 'portal_user', self::PORTAL_USER_ID, 'Hélène Lambert'));

        try {
            $this->commands()->dispatch(new UpdateClientCommunicationPreferences(self::CLINIC_ID, $clientId, null, ['marketing' => ['sms']], 'clinic', 'staff', self::STAFF_ID, 'Julie Martin', $rendered));
            self::fail('A stale sheet must be refused.');
        } catch (StaleConsentsException) {
        }

        $consents = $this->queries()->ask(new GetClientConsents(self::CLINIC_ID, [$clientId]));
        self::assertIsArray($consents);
        self::assertInstanceOf(ClientConsentsView::class, $consents[$clientId]);
        self::assertNull($consents[$clientId]->consentFor('marketing'));
    }

    public function testTheHistoryIsPagedNewestFirstAcrossTheClientsOfTheClinic(): void
    {
        $first  = $this->create(self::CLINIC_ID, ['marketing' => ['sms', 'email']]);
        $second = $this->create(self::CLINIC_ID, ['appointment_reminders' => ['sms']]);
        $other  = $this->create(self::OTHER_CLINIC_ID, ['marketing' => ['sms']]);
        // Spread the rows over distinct dates: the second client's grant is the newest.
        $this->connection()->executeStatement('UPDATE client__consent_history SET at = :at WHERE client_id = :id', ['at' => '2026-10-01 08:00:00', 'id' => Uuid::fromString($first)->toBinary()]);
        $this->connection()->executeStatement('UPDATE client__consent_history SET at = :at WHERE client_id = :id', ['at' => '2026-10-02 08:00:00', 'id' => Uuid::fromString($second)->toBinary()]);

        $page = $this->queries()->ask(new ListClientConsentHistory(self::CLINIC_ID, [$first, $second, $other], null, 2));
        self::assertInstanceOf(CursorPage::class, $page);
        $items = $this->items($page);
        self::assertSame([[$second, 'sms'], [$first, 'email']], array_map(static fn (ConsentHistoryItemView $item): array => [$item->clientId, $item->channel], $items));
        self::assertSame('2026-10-02 08:00:00', $items[0]->at->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $items[0]->at->getTimezone()->getName());
        self::assertFalse($items[0]->approximate);
        self::assertNotNull($page->nextCursor);

        $next = $this->queries()->ask(new ListClientConsentHistory(self::CLINIC_ID, [$first, $second, $other], $page->nextCursor, 2));
        self::assertInstanceOf(CursorPage::class, $next);
        self::assertSame([[$first, 'sms']], array_map(static fn (ConsentHistoryItemView $item): array => [$item->clientId, $item->channel], $this->items($next)));
        self::assertNull($next->nextCursor);
    }

    /** A consent recorded before the history is taken over as an approximate grant. */
    public function testAnApproximateTakeOverIsReadBackAsSuch(): void
    {
        $clientId = $this->create(self::CLINIC_ID, []);
        $this->history()->append([new ConsentHistoryEntry(
            ClinicId::fromString(self::CLINIC_ID),
            ClientId::fromString($clientId),
            new ConsentChange(ConsentKind::MARKETING, ContactChannel::SMS, ConsentChangeAction::GRANTED),
            new \DateTimeImmutable('2024-03-01 09:30:00', new \DateTimeZone('Europe/Paris')),
            ConsentSource::SYSTEM,
            new ConsentActor(ConsentActorType::SYSTEM, null, 'Reprise'),
            approximate: true,
        )]);

        $page = $this->queries()->ask(new ListClientConsentHistory(self::CLINIC_ID, [$clientId]));
        self::assertInstanceOf(CursorPage::class, $page);
        $items = $this->items($page);
        self::assertCount(1, $items);
        self::assertTrue($items[0]->approximate);
        self::assertSame(['marketing', 'sms', 'granted', 'system', 'system', null, 'Reprise', null], self::describe($items[0]));
        self::assertSame('2024-03-01 08:30:00', $items[0]->at->format('Y-m-d H:i:s'));
    }

    public function testConsentsOfAnotherShapeAreLeftOut(): void
    {
        $clientId = $this->create(self::CLINIC_ID, []);
        $other    = $this->create(self::OTHER_CLINIC_ID, ['marketing' => ['sms']]);
        $odd      = $this->create(self::CLINIC_ID, []);
        $this->connection()->executeStatement(
            'UPDATE client__clients SET communication_preferences = :json WHERE id = :id',
            ['json' => '{"preferredChannel": 3, "consents": [{"kind": "telepathy", "channels": ["sms"], "grantedAt": "2026-01-01T00:00:00+00:00"}, {"kind": "billing"}, "x", {"kind": "marketing", "channels": ["sms", 4], "grantedAt": "2026-01-01T00:00:00+00:00"}]}', 'id' => Uuid::fromString($clientId)->toBinary()],
        );
        $this->connection()->executeStatement('UPDATE client__clients SET communication_preferences = :json WHERE id = :id', ['json' => '"oops"', 'id' => Uuid::fromString($odd)->toBinary()]);

        $consents = $this->queries()->ask(new GetClientConsents(self::CLINIC_ID, [$clientId, $other, $odd]));
        self::assertIsArray($consents);
        self::assertSame([$clientId, $odd], array_keys($consents), 'a client of another clinic is absent');
        self::assertInstanceOf(ClientConsentsView::class, $consents[$clientId]);
        self::assertNull($consents[$clientId]->preferredChannel);
        self::assertSame(['marketing'], array_keys($consents[$clientId]->consents));
        self::assertSame(['sms'], $consents[$clientId]->consentFor('marketing')?->channels);
        self::assertInstanceOf(ClientConsentsView::class, $consents[$odd]);
        self::assertSame([], $consents[$odd]->consents);
    }

    public function testAClientOfAnotherClinicCannotBeLocked(): void
    {
        $clientId = $this->create(self::OTHER_CLINIC_ID, []);

        $this->expectException(ClientNotFoundException::class);

        $this->entityManager()->wrapInTransaction(fn () => $this->clients()->getForUpdate(ClinicId::fromString(self::CLINIC_ID), ClientId::fromString($clientId)));
    }

    public function testLockingReadsTheCurrentRecord(): void
    {
        $clientId = $this->create(self::CLINIC_ID, ['marketing' => ['sms']]);
        $client   = $this->entityManager()->wrapInTransaction(fn () => $this->clients()->getForUpdate(ClinicId::fromString(self::CLINIC_ID), ClientId::fromString($clientId)));

        self::assertSame($clientId, $client->id()->toString());
        self::assertNotNull($client->communicationPreferences()->consents[0] ?? null);
        self::assertSame(1, $client->version());
    }

    /**
     * @param array<string, list<string>> $consents
     */
    private function create(string $clinicId, array $consents): string
    {
        $id = $this->commands()->dispatch(new CreateClient(
            clinicId: $clinicId,
            firstName: 'Hélène',
            lastName: 'Lambert',
            contactMethods: [new ContactMethodDto('email', 'home', 'h.lambert@example.com', true)],
            channelsByConsentKind: $consents,
            actorType: 'staff',
            actorId: self::STAFF_ID,
            authorLabel: 'Julie Martin',
        ));
        \assert(\is_string($id));

        return $id;
    }

    /**
     * @param CursorPage<mixed> $page
     *
     * @return list<ConsentHistoryItemView>
     */
    private function items(CursorPage $page): array
    {
        return array_map(static function (mixed $item): ConsentHistoryItemView {
            \assert($item instanceof ConsentHistoryItemView);

            return $item;
        }, $page->items);
    }

    /**
     * @return array{string, string, string, string, string, ?string, string, ?string}
     */
    private static function describe(ConsentHistoryItemView $item): array
    {
        return [$item->kind, $item->channel, $item->action, $item->source, $item->actorType, $item->actorId, $item->authorLabel, $item->wordingVersion];
    }

    private function history(): ConsentHistoryRepositoryInterface
    {
        $history = self::getContainer()->get(ConsentHistoryRepositoryInterface::class);
        \assert($history instanceof ConsentHistoryRepositoryInterface);

        return $history;
    }

    private function clients(): ClientRepositoryInterface
    {
        $clients = self::getContainer()->get(ClientRepositoryInterface::class);
        \assert($clients instanceof ClientRepositoryInterface);

        return $clients;
    }

    private function commands(): CommandBusInterface
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus;
    }

    private function queries(): QueryBusInterface
    {
        $bus = self::getContainer()->get(QueryBusInterface::class);
        \assert($bus instanceof QueryBusInterface);

        return $bus;
    }

    private function entityManager(): EntityManagerInterface
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);

        return $connection;
    }
}
