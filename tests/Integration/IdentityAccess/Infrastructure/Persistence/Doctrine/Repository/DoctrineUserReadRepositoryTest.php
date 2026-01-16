<?php

declare(strict_types=1);

namespace App\Tests\Integration\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\Fixtures\IdentityAccess\Factory\BackofficeUserFactory;
use App\Fixtures\IdentityAccess\Factory\ClinicUserFactory;
use App\Fixtures\IdentityAccess\Factory\PortalUserFactory;
use App\IdentityAccess\Application\Port\UserReadRepositoryInterface;
use App\IdentityAccess\Domain\ValueObject\UserStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineUserReadRepositoryTest extends KernelTestCase
{
    use Factories;

    private UserReadRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $repository = static::getContainer()->get(UserReadRepositoryInterface::class);
        \assert($repository instanceof UserReadRepositoryInterface);
        $this->repository = $repository;
    }

    public function testListAllReturnsAllUsers(): void
    {
        ClinicUserFactory::createOne(['email' => 'clinic@example.com']);
        PortalUserFactory::createOne(['email' => 'portal@example.com']);
        BackofficeUserFactory::createOne(['email' => 'backoffice@example.com']);

        $result = $this->repository->listAll();

        self::assertCount(3, $result->users);
        self::assertSame(3, $result->total);
    }

    public function testListAllOrdersByCreatedAtDesc(): void
    {
        ClinicUserFactory::createOne([
            'email'     => 'first@example.com',
            'createdAt' => new \DateTimeImmutable('2024-01-01 10:00:00'),
        ]);
        ClinicUserFactory::createOne([
            'email'     => 'second@example.com',
            'createdAt' => new \DateTimeImmutable('2024-01-03 10:00:00'),
        ]);
        ClinicUserFactory::createOne([
            'email'     => 'third@example.com',
            'createdAt' => new \DateTimeImmutable('2024-01-02 10:00:00'),
        ]);

        $result = $this->repository->listAll();

        self::assertCount(3, $result->users);
        self::assertSame('second@example.com', $result->users[0]->email);
        self::assertSame('third@example.com', $result->users[1]->email);
        self::assertSame('first@example.com', $result->users[2]->email);
    }

    public function testListAllFiltersBySearchOnEmail(): void
    {
        ClinicUserFactory::createOne(['email' => 'john.doe@example.com']);
        ClinicUserFactory::createOne(['email' => 'jane.smith@example.com']);
        ClinicUserFactory::createOne(['email' => 'bob.johnson@example.com']);

        $result = $this->repository->listAll(search: 'john');

        self::assertCount(2, $result->users);
        $emails = array_map(static fn ($user) => $user->email, $result->users);
        self::assertContains('john.doe@example.com', $emails);
        self::assertContains('bob.johnson@example.com', $emails);
    }

    public function testListAllSearchIsPartialMatch(): void
    {
        ClinicUserFactory::createOne(['email' => 'test@example.com']);
        ClinicUserFactory::createOne(['email' => 'testing@example.com']);
        ClinicUserFactory::createOne(['email' => 'other@example.com']);

        $result = $this->repository->listAll(search: 'test');

        self::assertCount(2, $result->users);
    }

    public function testListAllSearchIsCaseInsensitive(): void
    {
        ClinicUserFactory::createOne(['email' => 'John.Doe@Example.COM']);
        ClinicUserFactory::createOne(['email' => 'other@example.com']);

        $result = $this->repository->listAll(search: 'JOHN');

        self::assertCount(1, $result->users);
        self::assertStringContainsStringIgnoringCase('john', $result->users[0]->email);
    }

    public function testListAllFiltersByTypeClinic(): void
    {
        ClinicUserFactory::createOne(['email' => 'clinic@example.com']);
        PortalUserFactory::createOne(['email' => 'portal@example.com']);
        BackofficeUserFactory::createOne(['email' => 'backoffice@example.com']);

        $result = $this->repository->listAll(type: 'CLINIC');

        self::assertCount(1, $result->users);
        self::assertSame('clinic@example.com', $result->users[0]->email);
        self::assertSame('CLINIC', $result->users[0]->type);
    }

    public function testListAllFiltersByTypePortal(): void
    {
        ClinicUserFactory::createOne();
        PortalUserFactory::createOne(['email' => 'portal@example.com']);
        BackofficeUserFactory::createOne();

        $result = $this->repository->listAll(type: 'PORTAL');

        self::assertCount(1, $result->users);
        self::assertSame('portal@example.com', $result->users[0]->email);
        self::assertSame('PORTAL', $result->users[0]->type);
    }

    public function testListAllFiltersByTypeBackoffice(): void
    {
        ClinicUserFactory::createOne();
        PortalUserFactory::createOne();
        BackofficeUserFactory::createOne(['email' => 'backoffice@example.com']);

        $result = $this->repository->listAll(type: 'BACKOFFICE');

        self::assertCount(1, $result->users);
        self::assertSame('backoffice@example.com', $result->users[0]->email);
        self::assertSame('BACKOFFICE', $result->users[0]->type);
    }

    public function testListAllFiltersByStatusActive(): void
    {
        ClinicUserFactory::createOne(['email' => 'active@example.com', 'status' => UserStatus::ACTIVE]);
        ClinicUserFactory::createOne(['email' => 'disabled@example.com', 'status' => UserStatus::DISABLED]);

        $result = $this->repository->listAll(status: 'ACTIVE');

        self::assertCount(1, $result->users);
        self::assertSame('active@example.com', $result->users[0]->email);
        self::assertSame('ACTIVE', $result->users[0]->status);
    }

    public function testListAllFiltersByStatusDisabled(): void
    {
        ClinicUserFactory::createOne(['status' => UserStatus::ACTIVE]);
        ClinicUserFactory::createOne(['email' => 'disabled@example.com', 'status' => UserStatus::DISABLED]);

        $result = $this->repository->listAll(status: 'DISABLED');

        self::assertCount(1, $result->users);
        self::assertSame('disabled@example.com', $result->users[0]->email);
        self::assertSame('DISABLED', $result->users[0]->status);
    }

    public function testListAllCombinesSearchAndTypeFilters(): void
    {
        ClinicUserFactory::createOne(['email' => 'clinic.john@example.com']);
        PortalUserFactory::createOne(['email' => 'portal.john@example.com']);
        ClinicUserFactory::createOne(['email' => 'clinic.jane@example.com']);

        $result = $this->repository->listAll(search: 'john', type: 'PORTAL');

        self::assertCount(1, $result->users);
        self::assertSame('portal.john@example.com', $result->users[0]->email);
        self::assertSame('PORTAL', $result->users[0]->type);
    }

    public function testListAllCombinesAllFilters(): void
    {
        ClinicUserFactory::createOne([
            'email'  => 'clinic.john.active@example.com',
            'status' => UserStatus::ACTIVE,
        ]);
        ClinicUserFactory::createOne([
            'email'  => 'clinic.john.disabled@example.com',
            'status' => UserStatus::DISABLED,
        ]);
        PortalUserFactory::createOne([
            'email'  => 'portal.john.active@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $result = $this->repository->listAll(search: 'john', type: 'CLINIC', status: 'ACTIVE');

        self::assertCount(1, $result->users);
        self::assertSame('clinic.john.active@example.com', $result->users[0]->email);
        self::assertSame('CLINIC', $result->users[0]->type);
        self::assertSame('ACTIVE', $result->users[0]->status);
    }

    public function testListAllReturnsEmptyWhenNoMatches(): void
    {
        ClinicUserFactory::createOne(['email' => 'test@example.com']);

        $result = $this->repository->listAll(search: 'nonexistent');

        self::assertCount(0, $result->users);
        self::assertSame(0, $result->total);
    }

    public function testListAllReturnsUserWithEmailVerified(): void
    {
        $verifiedAt = new \DateTimeImmutable('2024-01-15 10:30:00');

        ClinicUserFactory::createOne([
            'email'           => 'verified@example.com',
            'emailVerifiedAt' => $verifiedAt,
        ]);

        $result = $this->repository->listAll();

        self::assertCount(1, $result->users);
        self::assertNotNull($result->users[0]->emailVerifiedAt);
        self::assertSame(
            $verifiedAt->format('Y-m-d H:i:s'),
            $result->users[0]->emailVerifiedAt->format('Y-m-d H:i:s')
        );
    }

    public function testListAllReturnsUserWithNullEmailVerifiedAt(): void
    {
        ClinicUserFactory::createOne([
            'email'           => 'unverified@example.com',
            'emailVerifiedAt' => null,
        ]);

        $result = $this->repository->listAll();

        self::assertCount(1, $result->users);
        self::assertNull($result->users[0]->emailVerifiedAt);
    }

    public function testListAllReturnsUserListItemWithAllProperties(): void
    {
        $createdAt  = new \DateTimeImmutable('2024-01-01 09:00:00');
        $verifiedAt = new \DateTimeImmutable('2024-01-02 10:00:00');

        $user = ClinicUserFactory::createOne([
            'email'           => 'complete@example.com',
            'status'          => UserStatus::ACTIVE,
            'emailVerifiedAt' => $verifiedAt,
            'createdAt'       => $createdAt,
        ]);

        $result = $this->repository->listAll();

        self::assertCount(1, $result->users);

        $userItem = $result->users[0];
        self::assertSame($user->getId()->toString(), $userItem->id);
        self::assertSame('complete@example.com', $userItem->email);
        self::assertSame('CLINIC', $userItem->type);
        self::assertSame('ACTIVE', $userItem->status);
        self::assertNotNull($userItem->emailVerifiedAt);
        self::assertSame(
            $verifiedAt->format('Y-m-d H:i:s'),
            $userItem->emailVerifiedAt->format('Y-m-d H:i:s')
        );
        self::assertSame(
            $createdAt->format('Y-m-d H:i:s'),
            $userItem->createdAt->format('Y-m-d H:i:s')
        );
    }

    public function testListAllIgnoresEmptySearchString(): void
    {
        ClinicUserFactory::createOne(['email' => 'user1@example.com']);
        ClinicUserFactory::createOne(['email' => 'user2@example.com']);

        $result = $this->repository->listAll(search: '');

        self::assertCount(2, $result->users);
    }

    public function testListAllIgnoresEmptyTypeString(): void
    {
        ClinicUserFactory::createOne();
        PortalUserFactory::createOne();

        $result = $this->repository->listAll(type: '');

        self::assertCount(2, $result->users);
    }

    public function testListAllIgnoresEmptyStatusString(): void
    {
        ClinicUserFactory::createOne(['status' => UserStatus::ACTIVE]);
        ClinicUserFactory::createOne(['status' => UserStatus::DISABLED]);

        $result = $this->repository->listAll(status: '');

        self::assertCount(2, $result->users);
    }

    public function testListAllWithMixedUserTypes(): void
    {
        ClinicUserFactory::createMany(3);
        PortalUserFactory::createMany(2);
        BackofficeUserFactory::createOne();

        $result = $this->repository->listAll();

        self::assertCount(6, $result->users);
        self::assertSame(6, $result->total);

        $types = array_unique(array_map(static fn ($user) => $user->type, $result->users));
        self::assertContains('CLINIC', $types);
        self::assertContains('PORTAL', $types);
        self::assertContains('BACKOFFICE', $types);
    }
}
