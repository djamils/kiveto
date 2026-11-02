<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Adapter;

use App\Context\Client\Application\Query\GetClientConsents\ClientConsentsView;
use App\Context\Client\Application\Query\GetClientConsents\ClientConsentView;
use App\Context\Client\Application\Query\GetClientConsents\GetClientConsents;
use App\Context\Client\Application\Query\ListClientConsentHistory\ConsentHistoryItemView;
use App\Context\Client\Application\Query\ListClientConsentHistory\ListClientConsentHistory;
use App\Context\ClientPortal\Application\Port\DataExportReadyMessage;
use App\Context\ClientPortal\Infrastructure\Adapter\Client\QueryBusClientConsents;
use App\Context\ClientPortal\Infrastructure\Adapter\IdentityAccess\QueryBusOwnerAccounts;
use App\Context\ClientPortal\Infrastructure\Adapter\Notification\CommandBusNotificationRequester;
use App\Shared\Application\Pagination\CursorPage;
use App\System\IdentityAccess\Application\Query\GetPortalAccount\GetPortalAccount;
use App\System\IdentityAccess\Application\Query\GetPortalAccount\PortalAccount;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use App\Tests\Support\AnsweringQueryBus;
use App\Tests\Unit\Context\ClientPortal\Support\AnsweringCommandBus;
use PHPUnit\Framework\TestCase;

final class DataExportAdaptersTest extends TestCase
{
    public function testTheOwnerAccountComesFromIdentityAccess(): void
    {
        $bus      = new AnsweringQueryBus([GetPortalAccount::class => static fn (GetPortalAccount $q): ?PortalAccount => 'user-1' === $q->userId ? new PortalAccount('user-1', 's@example.com', '+33612345678', true) : null]);
        $accounts = new QueryBusOwnerAccounts($bus);

        $account = $accounts->accountOf('user-1');
        self::assertSame(['user-1', '+33612345678', 's@example.com', true, 's@example.com'], [$account?->userId, $account?->phone, $account?->email, $account?->emailVerified, $account?->verifiedEmail()]);
        self::assertNull($accounts->accountOf('user-2'));
    }

    public function testTheConsentsAreMergedPerKindAndTheWholeHistoryIsRead(): void
    {
        $at      = new \DateTimeImmutable('2026-10-10 12:00:00', new \DateTimeZone('UTC'));
        $records = [
            'client-1' => new ClientConsentsView('client-1', 'sms', false, [
                'marketing'             => new ClientConsentView('marketing', ['email'], $at),
                'appointment_reminders' => new ClientConsentView('appointment_reminders', ['email'], $at),
            ]),
            'client-2' => new ClientConsentsView('client-2', null, true, [
                'appointment_reminders' => new ClientConsentView('appointment_reminders', ['email', 'sms'], $at),
            ]),
        ];
        $history = static fn (string $id, string $action): ConsentHistoryItemView => new ConsentHistoryItemView($id, 'client-1', 'marketing', 'email', $action, $at, 'portal', 'portal_user', 'user-1', 'Sophie Martin', null, false);
        $bus     = new AnsweringQueryBus([
            GetClientConsents::class        => $records,
            ListClientConsentHistory::class => static fn (ListClientConsentHistory $q): CursorPage => null === $q->cursor
                ? new CursorPage([$history('h-1', 'granted')], 'next')
                : new CursorPage([$history('h-2', 'withdrawn')], null),
        ]);

        $export = new QueryBusClientConsents($bus)->exportOf('clinic-1', ['client-1', 'client-2']);

        self::assertSame([
            ['kind' => 'appointment_reminders', 'channels' => ['sms', 'email']],
            ['kind' => 'marketing', 'channels' => ['email']],
        ], $export['consents']);
        self::assertSame([['marketing', 'email', 'granted', 'portal', $at], ['marketing', 'email', 'withdrawn', 'portal', $at]], array_map(static fn (array $c): array => [$c['kind'], $c['channel'], $c['action'], $c['source'], $c['at']], $export['history']));

        $consents = $bus->asked[0];
        self::assertInstanceOf(GetClientConsents::class, $consents);
        self::assertSame(['clinic-1', ['client-1', 'client-2']], [$consents->clinicId, $consents->clientIds]);
        $second = $bus->asked[2];
        self::assertInstanceOf(ListClientConsentHistory::class, $second);
        self::assertSame(['clinic-1', ['client-1', 'client-2'], 'next', 50], [$second->clinicId, $second->clientIds, $second->cursor, $second->limit]);
    }

    public function testTheReadyNoticeIsAnEmailSentByKivetoWithoutLink(): void
    {
        $bus = new AnsweringCommandBus(static fn (): string => 'message-id');

        new CommandBusNotificationRequester($bus)->requestDataExportReady(new DataExportReadyMessage('export-1', 'user-1', 's@example.com', 'Sophie Martin', 'Sophie', '04/11/2026 à 11:02'));

        $notice = $bus->commands[0];
        self::assertInstanceOf(RequestNotification::class, $notice);
        self::assertSame(['PORTAL_DATA_EXPORT_READY', 'EMAIL', 's@example.com', 'Sophie Martin', null, null, 'portal_data_export', 'export-1', 'portal-data-export-ready:export-1'], [
            $notice->purpose, $notice->channel, $notice->recipientAddress, $notice->recipientName, $notice->clinicId, $notice->clientId, $notice->subjectType, $notice->subjectId, $notice->idempotencyKey,
        ]);
        self::assertSame(['ownerFirstName' => 'Sophie', 'expiresAtLabel' => '04/11/2026 à 11:02'], $notice->parameters);
        self::assertSame('user-1', $notice->accountId, 'masked with its account');
    }
}
