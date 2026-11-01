<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Adapter;

use App\Context\Client\Application\Query\ListClientsByMobilePhone\ListClientsByMobilePhone;
use App\Context\Client\Application\Query\ListClientsByMobilePhone\MobileClientMatch;
use App\Context\ClientPortal\Application\Port\MobileFileMatch;
use App\Context\ClientPortal\Infrastructure\Adapter\Client\QueryBusClientDirectory;
use App\Context\ClientPortal\Infrastructure\Adapter\Notification\CommandBusLinkRequestBell;
use App\Context\ClientPortal\Infrastructure\Security\SuggestionIdSigner;
use App\System\Notification\Application\Command\PostInAppNotification\PostInAppNotification;
use App\Tests\Support\AnsweringQueryBus;
use App\Tests\Unit\Context\ClientPortal\Support\AnsweringCommandBus;
use PHPUnit\Framework\TestCase;

/** The S7b adapters: the cross-clinic mobile lookup, the opaque suggestion id, the « Rattachement » bell. */
final class LinkSuggestionAdaptersTest extends TestCase
{
    public function testTheMobileMatchesComeFromTheClientContext(): void
    {
        $bus = new AnsweringQueryBus([ListClientsByMobilePhone::class => [new MobileClientMatch('clinic-1', 'client-1', 'Sophie', 'Martin', ['Moka'])]]);

        $matches = new QueryBusClientDirectory($bus)->mobileMatches('+33612345678');

        self::assertSame(
            [['clinic-1', 'client-1', 'Sophie', 'Martin', ['Moka']]],
            array_map(static fn (MobileFileMatch $m): array => [$m->clinicId, $m->clientId, $m->firstName, $m->lastName, $m->animalNames], $matches),
        );
        $query = $bus->asked[0];
        self::assertInstanceOf(ListClientsByMobilePhone::class, $query);
        self::assertSame('+33612345678', $query->phone);
    }

    public function testTheSuggestionIdIsAKeyedHashOfTheAccountAndTheClinic(): void
    {
        $signer = new SuggestionIdSigner('secret');
        $id     = $signer->idOf('user-1', 'clinic-1');

        self::assertSame(hash_hmac('sha256', 'link-suggestion|user-1|clinic-1', 'secret'), $id);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $id);
        self::assertSame($id, $signer->idOf('user-1', 'clinic-1'), 'stable');
        self::assertNotSame($id, $signer->idOf('user-2', 'clinic-1'));
        self::assertNotSame($id, $signer->idOf('user-1', 'clinic-2'));
        self::assertNotSame($id, new SuggestionIdSigner('other')->idOf('user-1', 'clinic-1'));
    }

    public function testTheBellOpensTheRequestOfTheAccount(): void
    {
        $bus  = new AnsweringCommandBus(static fn (object $command): mixed => null);
        $bell = new CommandBusLinkRequestBell($bus);

        $bell->announce('clinic-1', ['asv-1', 'asv-2', 'asv-1'], 'user-1', 'portal-link-request:l1', 'Demande de rattachement Portal', 'Sophie Martin');
        $bell->announce('clinic-1', [], 'user-1', 'portal-link-request:l2', 'Demande', 'Personne');

        self::assertCount(1, $bus->commands);
        $command = $bus->commands[0];
        self::assertInstanceOf(PostInAppNotification::class, $command);
        self::assertSame(
            ['clinic-1', ['asv-1', 'asv-2'], 'PORTAL_REQUEST', 'Demande de rattachement Portal', 'Sophie Martin', 'clinic_portal_link_requests_show', ['userId' => 'user-1'], 'portal-link-request:l1'],
            [$command->clinicId, $command->userIds, $command->kind, $command->title, $command->detail, $command->route, $command->routeParams, $command->idempotencyKey],
        );
    }
}
