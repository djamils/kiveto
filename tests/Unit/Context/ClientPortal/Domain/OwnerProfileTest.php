<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Domain;

use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Shared\Domain\ValueObject\PostalAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OwnerProfileTest extends TestCase
{
    public function testAProfileIsCopiedFromTheClientAndOnlyItsTermsMove(): void
    {
        $address = PostalAddress::create('12 rue des Lilas', 'Lyon', 'FR', null, '69003');
        $profile = OwnerProfile::createFromClient('user-1', 'Sophie', 'Martin', $address, 'v1', new \DateTimeImmutable('2026-10-29'));

        self::assertSame(['user-1', 'Sophie', 'Martin', $address, 'v1', '2026-10-29', '2026-10-29'], [
            $profile->userId(), $profile->firstName(), $profile->lastName(), $profile->address(), $profile->termsVersion(), $profile->termsAcceptedAt()->format('Y-m-d'), $profile->createdAt()->format('Y-m-d'),
        ]);

        $profile->acceptTerms('v2', new \DateTimeImmutable('2026-12-01'));
        self::assertSame(['v2', '2026-12-01', '2026-10-29', 'Sophie'], [$profile->termsVersion(), $profile->termsAcceptedAt()->format('Y-m-d'), $profile->createdAt()->format('Y-m-d'), $profile->firstName()]);

        $restored = OwnerProfile::reconstitute('user-2', 'Paul', 'Durand', null, 'v1', new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-09-01'));
        self::assertSame(['user-2', null, '2026-10-01', '2026-09-01'], [$restored->userId(), $restored->address(), $restored->termsAcceptedAt()->format('Y-m-d'), $restored->createdAt()->format('Y-m-d')]);
    }

    /** S7a: an owner without invitation enters their names; no address yet. */
    public function testARegisteredProfileKeepsTheEnteredNamesTrimmed(): void
    {
        $profile = OwnerProfile::register('user-1', ' Sophie ', 'Martin  ', 'v1', new \DateTimeImmutable('2026-11-02'));

        self::assertSame(['user-1', 'Sophie', 'Martin', null, 'v1', '2026-11-02', '2026-11-02'], [
            $profile->userId(), $profile->firstName(), $profile->lastName(), $profile->address(), $profile->termsVersion(), $profile->termsAcceptedAt()->format('Y-m-d'), $profile->createdAt()->format('Y-m-d'),
        ]);

        $this->expectExceptionObject(new \InvalidArgumentException('A profile name has 1 to 60 characters.'));
        OwnerProfile::register('user-2', 'Sophie', ' ', 'v1', new \DateTimeImmutable('2026-11-02'));
    }

    public function testTheOwnerRenamesAndMovesWithTrimmedNames(): void
    {
        $profile = OwnerProfile::createFromClient('user-1', 'Sophie', 'Martin', null, 'v1', new \DateTimeImmutable('2026-10-29'));

        $profile->rename('  Sophia ', str_repeat('é', 60));
        $address = PostalAddress::create('3 quai Rambaud', 'Lyon', 'FR', null, '69002');
        $profile->changeAddress($address);

        self::assertSame(['Sophia', str_repeat('é', 60), $address, 'v1'], [$profile->firstName(), $profile->lastName(), $profile->address(), $profile->termsVersion()]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideARefusedNameLeavesTheProfileUnchangedCases(): iterable
    {
        yield 'empty first name' => ['   ', 'Martin'];
        yield 'too long last name' => ['Sophie', str_repeat('a', 61)];
    }

    #[DataProvider('provideARefusedNameLeavesTheProfileUnchangedCases')]
    public function testARefusedNameLeavesTheProfileUnchanged(string $firstName, string $lastName): void
    {
        $profile = OwnerProfile::createFromClient('user-1', 'Sophie', 'Martin', null, 'v1', new \DateTimeImmutable('2026-10-29'));

        try {
            $profile->rename($firstName, $lastName);
            self::fail('The name should have been refused.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('A profile name has 1 to 60 characters.', $e->getMessage());
        }

        self::assertSame(['Sophie', 'Martin'], [$profile->firstName(), $profile->lastName()]);
    }
}
