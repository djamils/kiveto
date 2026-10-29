<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Notification;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\Notification\Application\Command\MarkAllInAppNotificationsRead\MarkAllInAppNotificationsRead;
use App\System\Notification\Application\Command\MarkInAppNotificationRead\MarkInAppNotificationRead;
use App\System\Notification\Application\Command\PostInAppNotification\PostInAppNotification;
use App\System\Notification\Application\Command\WithdrawInAppNotification\WithdrawInAppNotification;
use App\System\Notification\Application\Exception\InvalidNotificationRequestException;
use App\System\Notification\Application\Query\CountUnreadInAppNotifications\CountUnreadInAppNotifications;
use App\System\Notification\Application\Query\ListInAppNotifications\InAppNotificationView;
use App\System\Notification\Application\Query\ListInAppNotifications\ListInAppNotifications;
use App\System\Notification\Domain\Inbox\InAppKind;
use App\System\Notification\Domain\Inbox\InAppNotification;
use App\System\Notification\Domain\Inbox\InAppNotificationId;
use App\System\Notification\Domain\Inbox\InAppNotificationRepositoryInterface;
use App\System\Notification\Infrastructure\Persistence\Doctrine\Entity\InAppNotificationEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The bell through the real buses and tables: per user and per current clinic.
 */
final class InAppInboxTest extends KernelTestCase
{
    private const string CLINIC_A = '01960000-0000-7000-8000-00000000c0a1';
    private const string CLINIC_B = '01960000-0000-7000-8000-00000000c0b1';
    private const string VET      = '01960000-0000-7000-8000-0000000000f1';
    private const string ASSIST   = '01960000-0000-7000-8000-0000000000f2';

    /**
     * N11: only a replay (same key, same user) is ignored; any other error
     * surfaces instead of being hidden or truncated by INSERT IGNORE.
     */
    public function testOnlyAReplayIsIgnored(): void
    {
        $repository = self::getContainer()->get('test.' . InAppNotificationRepositoryInterface::class);
        \assert($repository instanceof InAppNotificationRepositoryInterface);
        $entry = static fn (string $id, string $route): InAppNotification => InAppNotification::reconstitute(InAppNotificationId::fromString($id), self::CLINIC_A, self::VET, InAppKind::CRITICAL_STOCK, 'Stock critique', '', $route, [], null, 'critical-stock:s-9', new \DateTimeImmutable('-1 hour'), null);

        self::assertTrue($repository->add($entry('01960000-0000-7000-8000-0000000000b7', 'clinic_stock')));
        self::assertFalse($repository->add($entry('01960000-0000-7000-8000-0000000000b8', 'clinic_stock')), 'replayed');

        $this->expectException(\Doctrine\DBAL\Exception\DriverException::class);
        $repository->add(InAppNotification::reconstitute(InAppNotificationId::fromString('01960000-0000-7000-8000-0000000000b9'), self::CLINIC_A, self::VET, InAppKind::CRITICAL_STOCK, 'Stock critique', '', str_repeat('r', 121), [], null, 'critical-stock:s-10', new \DateTimeImmutable('-1 hour'), null));
    }

    /**
     * AC 40: per user and clinic, read one, « Tout marquer lu », withdraw while unread.
     */
    public function testTheBellOfAUserInTheCurrentClinic(): void
    {
        self::assertSame(2, $this->post(self::CLINIC_A, [self::VET, self::ASSIST, self::VET], 'NEW_APPOINTMENT', 'new-appointment:a-1'));
        self::assertSame(0, $this->post(self::CLINIC_A, [self::VET], 'NEW_APPOINTMENT', 'new-appointment:a-1'), 'replayed: nothing new');
        $this->post(self::CLINIC_A, [self::VET], 'CRITICAL_STOCK', 'critical-stock:s-1');
        $this->post(self::CLINIC_B, [self::VET], 'SMS_QUOTA', 'sms-quota:c-b:2026-10:80');

        $list = $this->list(self::VET, self::CLINIC_A);
        self::assertSame(['CRITICAL_STOCK', 'NEW_APPOINTMENT'], array_map(static fn (InAppNotificationView $v): string => $v->kind, $list), 'current clinic only, newest first');
        self::assertSame(['Titre', 'Détail', 'clinic_agenda', ['date' => '2026-10-22'], 'CL', 'activite', false], [$list[1]->title, $list[1]->detail, $list[1]->route, $list[1]->routeParams, $list[1]->actorInitials, $list[1]->filter, $list[1]->read]);
        self::assertCount(1, $this->list(self::VET, self::CLINIC_A, 1));
        self::assertSame(2, $this->unread(self::VET, self::CLINIC_A));
        self::assertSame(1, $this->unread(self::VET, self::CLINIC_B));

        self::assertFalse($this->command(new MarkInAppNotificationRead($list[1]->id, self::ASSIST)), "someone else's entry");
        self::assertTrue($this->command(new MarkInAppNotificationRead($list[1]->id, self::VET)));
        self::assertFalse($this->command(new MarkInAppNotificationRead($list[1]->id, self::VET)), 'already read');
        self::assertSame(1, $this->unread(self::VET, self::CLINIC_A));

        // The appointment is cancelled: the assistant's unread entry disappears, the vet's read one stays.
        self::assertSame(1, $this->command(new WithdrawInAppNotification('new-appointment:a-1')));
        self::assertSame([], $this->list(self::ASSIST, self::CLINIC_A));
        self::assertCount(2, $this->list(self::VET, self::CLINIC_A));

        self::assertSame(1, $this->command(new MarkAllInAppNotificationsRead(self::VET, self::CLINIC_A)));
        self::assertSame(0, $this->unread(self::VET, self::CLINIC_A));
        self::assertSame(1, $this->unread(self::VET, self::CLINIC_B), 'the other clinic is untouched');
    }

    public function testOldEntriesAreNeitherListedNorKept(): void
    {
        $repository = self::getContainer()->get('test.' . InAppNotificationRepositoryInterface::class);
        \assert($repository instanceof InAppNotificationRepositoryInterface);
        $repository->add(InAppNotification::post(InAppNotificationId::fromString('01960000-0000-7000-8000-0000000000b9'), self::CLINIC_A, self::VET, InAppKind::EXPIRING_LOTS, 'Péremption proche', '', 'clinic_stock', [], null, 'expiring-lot:l-1', new \DateTimeImmutable('-31 days')));

        self::assertSame([], $this->list(self::VET, self::CLINIC_A));
        self::assertSame(0, $this->unread(self::VET, self::CLINIC_A));

        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $entity = $em->find(InAppNotificationEntity::class, Uuid::fromString('01960000-0000-7000-8000-0000000000b9'));
        self::assertInstanceOf(InAppNotificationEntity::class, $entity);
        self::assertSame(
            ['01960000-0000-7000-8000-0000000000b9', self::CLINIC_A, self::VET, 'EXPIRING_LOTS', 'Péremption proche', '', 'clinic_stock', [], null, 'expiring-lot:l-1', null],
            [$entity->getId()->toRfc4122(), $entity->getClinicId()->toRfc4122(), $entity->getUserId()->toRfc4122(), $entity->getKind(), $entity->getTitle(), $entity->getDetail(), $entity->getRoute(), $entity->getRouteParams(), $entity->getActorInitials(), $entity->getIdempotencyKey(), $entity->getReadAt()],
        );
        self::assertLessThan(new \DateTimeImmutable('-30 days'), $entity->getCreatedAt());
        $built = new InAppNotificationEntity(Uuid::v7(), Uuid::v7(), Uuid::v7(), 'SMS_QUOTA', 't', 'd', 'r', ['a' => 'b'], 'CL', 'k', new \DateTimeImmutable(), new \DateTimeImmutable());
        self::assertSame(['a' => 'b'], $built->getRouteParams());

        self::assertSame(1, $repository->purgeCreatedBefore(new \DateTimeImmutable('-30 days')));
        self::assertSame(0, $repository->purgeCreatedBefore(new \DateTimeImmutable('-30 days')));
    }

    public function testAnUnknownKindIsRefused(): void
    {
        $this->expectException(InvalidNotificationRequestException::class);

        $this->post(self::CLINIC_A, [self::VET], 'LAB_RESULT', 'lab:1');
    }

    /**
     * @param list<string> $users
     */
    private function post(string $clinic, array $users, string $kind, string $key): int
    {
        $created = $this->command(new PostInAppNotification($clinic, $users, $kind, 'Titre', 'Détail', 'clinic_agenda', ['date' => '2026-10-22'], $key, 'CL'));
        \assert(\is_int($created));

        return $created;
    }

    /**
     * @return list<InAppNotificationView>
     */
    private function list(string $user, string $clinic, ?int $limit = null): array
    {
        $list = $this->queries()->ask(new ListInAppNotifications($user, $clinic, $limit));
        \assert(\is_array($list));

        return array_values(array_filter($list, static fn (mixed $v): bool => $v instanceof InAppNotificationView));
    }

    private function unread(string $user, string $clinic): int
    {
        $count = $this->queries()->ask(new CountUnreadInAppNotifications($user, $clinic));
        \assert(\is_int($count));

        return $count;
    }

    private function command(object $command): mixed
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus->dispatch($command);
    }

    private function queries(): QueryBusInterface
    {
        $bus = self::getContainer()->get(QueryBusInterface::class);
        \assert($bus instanceof QueryBusInterface);

        return $bus;
    }
}
