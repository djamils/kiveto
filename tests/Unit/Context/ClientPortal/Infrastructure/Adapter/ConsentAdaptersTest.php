<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Adapter;

use App\Context\Client\Application\Command\SetClientConsent\SetClientConsent;
use App\Context\Client\Application\Query\GetClientConsents\ClientConsentsView;
use App\Context\Client\Application\Query\GetClientConsents\ClientConsentView;
use App\Context\Client\Application\Query\GetClientConsents\GetClientConsents;
use App\Context\Client\Application\Query\ListClientConsentHistory\ConsentHistoryItemView;
use App\Context\Client\Application\Query\ListClientConsentHistory\ListClientConsentHistory;
use App\Context\ClientPortal\Application\Port\ClientConsentChange;
use App\Context\ClientPortal\Domain\Exception\ConsentConflictException;
use App\Context\ClientPortal\Infrastructure\Adapter\Client\CommandBusClientConsents;
use App\Context\ClientPortal\Infrastructure\Adapter\Client\QueryBusClientConsents;
use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\CursorPage;
use App\Tests\Support\AnsweringQueryBus;
use App\Tests\Unit\Context\ClientPortal\Support\AnsweringCommandBus;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConsentAdaptersTest extends TestCase
{
    public function testTheConsentsOfEachFileAreRead(): void
    {
        $at  = new \DateTimeImmutable('2026-10-10 12:00:00', new \DateTimeZone('UTC'));
        $bus = new AnsweringQueryBus([GetClientConsents::class => [
            'client-1' => new ClientConsentsView('client-1', 'sms', true, ['marketing' => new ClientConsentView('marketing', ['sms', 'email'], $at)]),
        ]]);

        $records = new QueryBusClientConsents($bus)->consentsOf('clinic-1', ['client-1', 'client-2']);

        self::assertSame(['client-1'], array_keys($records));
        $record = $records['client-1'];
        self::assertSame(['client-1', 'sms', true], [$record->clientId, $record->preferredChannel, $record->archived]);
        self::assertSame(['marketing', ['sms', 'email'], $at], [$record->consents['marketing']->kind, $record->consents['marketing']->channels, $record->consents['marketing']->grantedAt]);
        $query = $bus->asked[0];
        self::assertInstanceOf(GetClientConsents::class, $query);
        self::assertSame(['clinic-1', ['client-1', 'client-2']], [$query->clinicId, $query->clientIds]);
    }

    public function testAHistoryBatchStartsAfterThePositionGivenAsTheClientCursor(): void
    {
        $at  = new \DateTimeImmutable('2026-10-20 10:00:00', new \DateTimeZone('UTC'));
        $bus = new AnsweringQueryBus([ListClientConsentHistory::class => new CursorPage([
            new ConsentHistoryItemView('h-1', 'client-1', 'marketing', 'email', 'withdrawn', $at, 'clinic', 'staff', 'member-1', 'Claire Lambert', 'v2', true),
        ], 'ignored')]);
        $adapter = new QueryBusClientConsents($bus);

        $rows = $adapter->historyOf('clinic-1', ['client-1'], ['at' => new \DateTimeImmutable('2026-10-20 12:00:00+02:00'), 'id' => 'ffffffff-ffff-ffff-ffff-ffffffffffff'], 50);
        $adapter->historyOf('clinic-1', ['client-1'], null, 10);

        self::assertSame(
            [['h-1', 'client-1', 'marketing', 'email', 'withdrawn', $at, 'clinic', 'staff', 'Claire Lambert', true]],
            array_map(static fn (ClientConsentChange $r): array => [$r->id, $r->clientId, $r->kind, $r->channel, $r->action, $r->at, $r->source, $r->actorType, $r->authorLabel, $r->approximate], $rows),
        );
        $first = $bus->asked[0];
        self::assertInstanceOf(ListClientConsentHistory::class, $first);
        self::assertSame(['clinic-1', ['client-1'], 50], [$first->clinicId, $first->clientIds, $first->limit]);
        self::assertSame(['at' => '2026-10-20 10:00:00', 'id' => 'ffffffff-ffff-ffff-ffff-ffffffffffff'], Cursor::decode((string) $first->cursor)->position());
        $second = $bus->asked[1];
        self::assertInstanceOf(ListClientConsentHistory::class, $second);
        self::assertSame([null, 10], [$second->cursor, $second->limit]);
    }

    public function testTheConsentIsWrittenAsThePortalUsersOwnChange(): void
    {
        $bus = new AnsweringCommandBus(static fn (): null => null);

        new CommandBusClientConsents($bus)->setConsent('clinic-1', 'client-1', 'marketing', ['sms'], 'user-1', 'Sophie Martin');

        $command = $bus->commands[0];
        self::assertInstanceOf(SetClientConsent::class, $command);
        self::assertSame(
            ['clinic-1', 'client-1', 'marketing', ['sms'], 'portal', 'portal_user', 'user-1', 'Sophie Martin', null],
            [$command->clinicId, $command->clientId, $command->kind, $command->channels, $command->source, $command->actorType, $command->actorId, $command->authorLabel, $command->wordingVersion],
        );
    }

    /**
     * @return iterable<string, array{\Throwable}>
     */
    public static function provideALockThatStillFailsIsAConflictCases(): iterable
    {
        yield 'version mismatch' => [OptimisticLockException::lockFailed(new \stdClass())];
        yield 'deadlock' => [new DeadlockException(new class('Deadlock found') extends \Exception implements DriverException {
            public function getSQLState(): string
            {
                return '40001';
            }
        }, null)];
    }

    #[DataProvider('provideALockThatStillFailsIsAConflictCases')]
    public function testALockThatStillFailsIsAConflict(\Throwable $failure): void
    {
        $bus = new AnsweringCommandBus(static fn (): never => throw $failure);

        try {
            new CommandBusClientConsents($bus)->setConsent('clinic-1', 'client-1', 'marketing', ['sms'], 'user-1', 'Sophie Martin');
            self::fail('A conflict was expected.');
        } catch (ConsentConflictException $conflict) {
            self::assertSame($failure, $conflict->getPrevious());
        }
    }
}
