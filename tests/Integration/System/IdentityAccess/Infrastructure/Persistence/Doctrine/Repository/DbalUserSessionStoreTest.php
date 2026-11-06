<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\System\IdentityAccess\Application\Port\UserSessionStoreInterface;
use App\System\IdentityAccess\Application\Query\ListUserSessions\UserSessionView;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalUserSessionStoreTest extends KernelTestCase
{
    private const string USER  = '01960000-0000-7000-8000-000000006d01';
    private const string OTHER = '01960000-0000-7000-8000-000000006d02';

    private const string S1 = '01960000-0000-7000-8000-000000006da1';
    private const string S2 = '01960000-0000-7000-8000-000000006da2';
    private const string S3 = '01960000-0000-7000-8000-000000006da3';
    private const string S4 = '01960000-0000-7000-8000-000000006da4';
    private const string S5 = '01960000-0000-7000-8000-000000006da5';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
    }

    public function testAnOpenedSessionIsFoundByItsDigest(): void
    {
        $sessions = $this->sessions();
        // A Paris time: the dates are stored in UTC and read back as UTC.
        $sessions->open(self::S1, self::USER, self::hash('s1'), 'Firefox', '203.0.113.7', new \DateTimeImmutable('2026-11-06 10:00:00', new \DateTimeZone('Europe/Paris')));

        self::assertNull($sessions->find(self::hash('unknown')));

        $found = $sessions->find(self::hash('s1'));
        self::assertNotNull($found);
        self::assertSame([self::S1, self::USER, '2026-11-06 09:00:00', 'UTC', false], [
            $found->id, $found->userId, $found->lastSeenAt->format('Y-m-d H:i:s'), $found->lastSeenAt->getTimezone()->getName(), $found->revoked,
        ]);
    }

    public function testAReplayedFirstRequestFindsItsRow(): void
    {
        $sessions = $this->sessions();
        $sessions->open(self::S1, self::USER, self::hash('s1'), 'Firefox', '203.0.113.7', $this->now);
        $sessions->open(self::S2, self::USER, self::hash('s1'), 'Firefox', '203.0.113.7', $this->now->modify('+1 second'));

        self::assertSame(self::S1, $sessions->find(self::hash('s1'))?->id);
        self::assertSame([self::S1], array_map(static fn (UserSessionView $view): string => $view->id, $sessions->listActive(self::USER, $this->now->modify('-1 day'), null)));
    }

    public function testASessionSeenAgainKeepsItsAddressWhenNoneIsKnown(): void
    {
        $sessions = $this->sessions();
        $sessions->open(self::S1, self::USER, self::hash('s1'), 'Firefox', '203.0.113.7', $this->now->modify('-2 hours'));

        $sessions->touch(self::hash('s1'), null, $this->now->modify('-1 hour'));
        self::assertSame(['203.0.113.7', '2026-11-06 08:00:00'], $this->ipAndLastSeen());

        $sessions->touch(self::hash('s1'), '198.51.100.4', $this->now);
        self::assertSame(['198.51.100.4', '2026-11-06 09:00:00'], $this->ipAndLastSeen());
        self::assertSame('2026-11-06 07:00:00', $sessions->listActive(self::USER, $this->now->modify('-1 day'), null)[0]->createdAt->format('Y-m-d H:i:s'));
    }

    public function testASignedOutSessionLeavesTheList(): void
    {
        $sessions = $this->sessions();
        $sessions->open(self::S1, self::USER, self::hash('s1'), null, null, $this->now);

        $sessions->end(self::hash('s1'), $this->now->modify('+1 minute'));
        $sessions->end(self::hash('s1'), $this->now->modify('+2 minutes'));

        self::assertTrue($sessions->find(self::hash('s1'))?->revoked);
        self::assertSame([], $sessions->listActive(self::USER, $this->now->modify('-1 day'), null));
        self::assertFalse($sessions->revoke(self::USER, self::S1, $this->now), 'an ended session is not revoked');
    }

    public function testOneSessionIsRevokedOnlyByItsAccount(): void
    {
        $sessions = $this->sessions();
        $sessions->open(self::S1, self::USER, self::hash('s1'), null, null, $this->now);

        self::assertFalse($sessions->revoke(self::OTHER, self::S1, $this->now), 'another account');
        self::assertFalse($sessions->find(self::hash('s1'))?->revoked);

        self::assertTrue($sessions->revoke(self::USER, self::S1, $this->now));
        self::assertFalse($sessions->revoke(self::USER, self::S1, $this->now), 'already revoked');
        self::assertTrue($sessions->find(self::hash('s1'))?->revoked);
    }

    public function testEverySessionButTheKeptOneIsRevoked(): void
    {
        $sessions = $this->sessions();
        $sessions->open(self::S1, self::USER, self::hash('s1'), null, null, $this->now);
        $sessions->open(self::S2, self::USER, self::hash('s2'), null, null, $this->now);
        $sessions->open(self::S3, self::USER, self::hash('s3'), null, null, $this->now);
        $sessions->open(self::S4, self::OTHER, self::hash('s4'), null, null, $this->now);
        $sessions->end(self::hash('s3'), $this->now);

        self::assertSame(1, $sessions->revokeAll(self::USER, self::hash('s1'), $this->now));
        self::assertSame([false, true, true, false], [
            $sessions->find(self::hash('s1'))?->revoked, $sessions->find(self::hash('s2'))?->revoked,
            $sessions->find(self::hash('s3'))?->revoked, $sessions->find(self::hash('s4'))?->revoked,
        ]);

        self::assertSame(1, $sessions->revokeAll(self::USER, null, $this->now), 'none kept');
        self::assertTrue($sessions->find(self::hash('s1'))?->revoked);
        self::assertFalse($sessions->find(self::hash('s4'))?->revoked, 'another account is untouched');
    }

    public function testTheActiveSessionsAreListedNewestFirstWithTheAskingOneFlagged(): void
    {
        $sessions = $this->sessions();
        $sessions->open(self::S1, self::USER, self::hash('s1'), 'Firefox', '203.0.113.7', $this->now->modify('-3 hours'));
        $sessions->open(self::S2, self::USER, self::hash('s2'), null, null, $this->now->modify('-1 hour'));
        $sessions->open(self::S3, self::USER, self::hash('revoked'), null, null, $this->now);
        $sessions->open(self::S4, self::USER, self::hash('old'), null, null, $this->now->modify('-31 days'));
        $sessions->open(self::S5, self::OTHER, self::hash('other'), null, null, $this->now);
        $sessions->revoke(self::USER, self::S3, $this->now);

        $views = $sessions->listActive(self::USER, $this->now->modify('-30 days'), self::hash('s1'));

        self::assertSame([[self::S2, false], [self::S1, true]], array_map(static fn (UserSessionView $view): array => [$view->id, $view->current], $views));
        self::assertSame([null, null, '2026-11-06 08:00:00', '2026-11-06 08:00:00'], [
            $views[0]->userAgent, $views[0]->ipAddress, $views[0]->createdAt->format('Y-m-d H:i:s'), $views[0]->lastSeenAt->format('Y-m-d H:i:s'),
        ]);
        self::assertSame(['Firefox', '203.0.113.7', '2026-11-06 06:00:00', 'UTC'], [
            $views[1]->userAgent, $views[1]->ipAddress, $views[1]->lastSeenAt->format('Y-m-d H:i:s'), $views[1]->createdAt->getTimezone()->getName(),
        ]);

        self::assertSame([false, false], array_map(static fn (UserSessionView $view): bool => $view->current, $sessions->listActive(self::USER, $this->now->modify('-30 days'), null)));
    }

    /**
     * @return array{?string, string}
     */
    private function ipAndLastSeen(): array
    {
        $view = $this->sessions()->listActive(self::USER, $this->now->modify('-1 day'), null)[0];

        return [$view->ipAddress, $view->lastSeenAt->format('Y-m-d H:i:s')];
    }

    private static function hash(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }

    private function sessions(): UserSessionStoreInterface
    {
        $store = self::getContainer()->get(UserSessionStoreInterface::class);
        \assert($store instanceof UserSessionStoreInterface);

        return $store;
    }
}
