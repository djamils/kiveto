<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command\UpdateBackofficeProfile;

use App\System\IdentityAccess\Application\Command\UpdateBackofficeProfile\UpdateBackofficeProfile;
use App\System\IdentityAccess\Application\Command\UpdateBackofficeProfile\UpdateBackofficeProfileHandler;
use App\System\IdentityAccess\Application\Port\BackofficeProfileStoreInterface;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryUsers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UpdateBackofficeProfileHandlerTest extends TestCase
{
    private const string ADMIN  = '01960000-0000-7000-8000-000000004b01';
    private const string CLINIC = '01960000-0000-7000-8000-000000004b02';

    private InMemoryUsers $users;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->users = new InMemoryUsers();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC')));

        foreach ([self::ADMIN => UserType::BACKOFFICE, self::CLINIC => UserType::CLINIC] as $id => $type) {
            $this->users->save(User::reconstitute(UserId::fromString($id), $id . '@kiveto.local', '$h', new \DateTimeImmutable(), UserStatus::ACTIVE, null, $type));
        }
    }

    public function testTheSettingsAreSavedWithATrimmedName(): void
    {
        $profiles = $this->createMock(BackofficeProfileStoreInterface::class);
        $profiles->expects(self::once())->method('save')->with(self::ADMIN, 'Djamil Soidiki', 'America/Martinique', true, false, $this->clock->now());

        $this->update($profiles, new UpdateBackofficeProfile(self::ADMIN, '  Djamil Soidiki  ', ' America/Martinique ', true, false));
    }

    public function testANameOfEightyCharactersIsAccepted(): void
    {
        $profiles = $this->createMock(BackofficeProfileStoreInterface::class);
        $profiles->expects(self::once())->method('save')->with(self::ADMIN, str_repeat('é', 80), 'Europe/Paris', false, true, $this->clock->now());

        $this->update($profiles, new UpdateBackofficeProfile(self::ADMIN, str_repeat('é', 80), 'Europe/Paris', false, true));
    }

    #[DataProvider('provideABlankOrTooLongNameIsRefusedCases')]
    public function testABlankOrTooLongNameIsRefused(string $name): void
    {
        $profiles = $this->createMock(BackofficeProfileStoreInterface::class);
        $profiles->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom affiché compte de 1 à 80 caractères.');

        $this->update($profiles, new UpdateBackofficeProfile(self::ADMIN, $name, 'Europe/Paris', true, true));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideABlankOrTooLongNameIsRefusedCases(): iterable
    {
        yield 'empty' => [''];

        yield 'blank' => ["  \t "];

        yield '81 characters' => [str_repeat('é', 81)];
    }

    public function testAnUnknownTimeZoneIsRefused(): void
    {
        $profiles = $this->createMock(BackofficeProfileStoreInterface::class);
        $profiles->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid timezone: "Mars/Olympus_Mons". Must be a valid IANA timezone.');

        $this->update($profiles, new UpdateBackofficeProfile(self::ADMIN, 'Djamil', 'Mars/Olympus_Mons', true, true));
    }

    #[DataProvider('provideOnlyABackOfficeAccountHasAConsoleProfileCases')]
    public function testOnlyABackOfficeAccountHasAConsoleProfile(string $userId): void
    {
        $profiles = $this->createMock(BackofficeProfileStoreInterface::class);
        $profiles->expects(self::never())->method('save');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Only back-office accounts have a console profile.');

        $this->update($profiles, new UpdateBackofficeProfile($userId, 'Djamil', 'Europe/Paris', true, true));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideOnlyABackOfficeAccountHasAConsoleProfileCases(): iterable
    {
        yield 'unknown account' => ['01960000-0000-7000-8000-000000004bff'];

        yield 'clinic account' => [self::CLINIC];
    }

    private function update(BackofficeProfileStoreInterface $profiles, UpdateBackofficeProfile $command): void
    {
        new UpdateBackofficeProfileHandler($this->users, $profiles, $this->clock)($command);
    }
}
