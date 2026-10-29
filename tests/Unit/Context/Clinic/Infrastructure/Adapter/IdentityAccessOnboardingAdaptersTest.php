<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Infrastructure\Adapter;

use App\Context\Clinic\Infrastructure\Adapter\IdentityAccess\IdentityAccessUserSearchAdapter;
use App\Context\Clinic\Infrastructure\Adapter\IdentityAccess\QueryBusInvitationStatusReader;
use App\System\IdentityAccess\Application\Port\UserReadRepositoryInterface;
use App\System\IdentityAccess\Application\Query\GetInvitationStatuses\GetInvitationStatuses;
use App\System\IdentityAccess\Application\Query\ListUsers\UserCollection;
use App\System\IdentityAccess\Application\Query\ListUsers\UserListItem;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;

/**
 * How Clinic reads IdentityAccess for onboarding: the accounts that may join
 * a team, and where invited members stand.
 */
final class IdentityAccessOnboardingAdaptersTest extends TestCase
{
    public function testTheSearchFindsActiveAndInvitedClinicAccounts(): void
    {
        $users = $this->createMock(UserReadRepositoryInterface::class);
        $users->expects(self::once())->method('listAll')->with('hugo', 'CLINIC', null)->willReturn(new UserCollection([
            $this->item('u-1', 'hugo.active@example.com', 'ACTIVE'),
            $this->item('u-2', 'hugo.off@example.com', 'DISABLED'),
            $this->item('u-3', 'hugo.invited@example.com', 'PENDING'),
            $this->item('u-4', 'hugo.other@example.com', 'ACTIVE'),
        ], 4));

        $found = new IdentityAccessUserSearchAdapter($users)->searchByEmail('hugo', 2);

        self::assertSame([['u-1', 'hugo.active@example.com'], ['u-3', 'hugo.invited@example.com']], array_map(static fn ($item): array => [$item->userId, $item->email], $found));
    }

    public function testTheSearchNeedsTwoCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new IdentityAccessUserSearchAdapter($this->createStub(UserReadRepositoryInterface::class))->searchByEmail('h', 5);
    }

    public function testInvitationStatusesComeFromIdentityAccess(): void
    {
        $bus    = new AnsweringQueryBus([GetInvitationStatuses::class => ['u-1' => 'PENDING_VALID', 'u-2' => 'ACTIVE', 'u-3' => null]]);
        $reader = new QueryBusInvitationStatusReader($bus);

        self::assertSame(['u-1' => 'PENDING_VALID', 'u-2' => 'ACTIVE'], $reader->statuses('clinic-1', ['u-1', 'u-2', 'u-3']));
        self::assertSame([], $reader->statuses('clinic-1', []));
        self::assertCount(1, $bus->asked);
        $query = $bus->asked[0];
        self::assertInstanceOf(GetInvitationStatuses::class, $query);
        self::assertSame(['clinic-1', ['u-1', 'u-2', 'u-3']], [$query->clinicId, $query->userIds]);
    }

    private function item(string $id, string $email, string $status): UserListItem
    {
        return new UserListItem($id, $email, 'CLINIC', $status, null, new \DateTimeImmutable());
    }
}
