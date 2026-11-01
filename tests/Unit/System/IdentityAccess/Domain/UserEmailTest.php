<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use PHPUnit\Framework\TestCase;

/** An owner proves an e-mail with a code (S5e, D8). */
final class UserEmailTest extends TestCase
{
    private \DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->at = new \DateTimeImmutable('2026-11-01 10:00:00');
    }

    public function testAnOwnerConfirmsAnAddress(): void
    {
        $owner = User::registerPortal(UserId::fromString('01960000-0000-7000-8000-0000000008e1'), PhoneNumber::fromString('+33612345678'), 'copied@example.com', $this->at);
        self::assertFalse($owner->hasVerifiedEmail(), 'copied from a client file');

        $owner->confirmEmail('proven@example.com', $this->at->modify('+1 hour'));

        self::assertSame(['proven@example.com', '11:00', true], [$owner->email(), $owner->emailVerifiedAt()?->format('H:i'), $owner->hasVerifiedEmail()]);
    }

    public function testAnUnverifiedAddressIsReleasedAndAVerifiedOneNever(): void
    {
        $owner = User::registerPortal(UserId::fromString('01960000-0000-7000-8000-0000000008e2'), PhoneNumber::fromString('+33612345679'), 'copied@example.com', $this->at);
        $owner->releaseUnverifiedEmail();
        self::assertNull($owner->email());
        self::assertFalse($owner->hasVerifiedEmail());

        $owner->confirmEmail('proven@example.com', $this->at);
        $this->expectException(\LogicException::class);
        $owner->releaseUnverifiedEmail();
    }

    public function testAStaffAccountNeverConfirmsAnAddressWithACode(): void
    {
        $staff = User::register(UserId::fromString('01960000-0000-7000-8000-0000000008e3'), 'vet@example.com', 'hash', $this->at, UserType::CLINIC);

        $this->expectException(\LogicException::class);
        $staff->confirmEmail('other@example.com', $this->at);
    }
}
