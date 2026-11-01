<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Application\Command\RequestPortalActionOtp\RequestPortalActionOtp;
use App\System\IdentityAccess\Application\Command\RequestPortalActionOtp\RequestPortalActionOtpHandler;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\OtpChallengeIssued;
use App\System\IdentityAccess\Application\Service\ActionOtpVerifier;
use App\System\IdentityAccess\Application\Service\PortalOwnerAccounts;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\AccountFakes;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryOtpChallenges;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryUsers;
use App\Tests\Unit\System\IdentityAccess\Support\PortalFakes;
use PHPUnit\Framework\TestCase;

/** In-memory doubles shared by the tests of the action codes and the e-mail change. */
abstract class PortalActionOtpTestCase extends TestCase
{
    protected const string ALICE       = '01960000-0000-7000-8000-0000000009e1';
    protected const string BOB         = '01960000-0000-7000-8000-0000000009e2';
    protected const string ALICE_PHONE = '+33612340901';
    protected const string BOB_PHONE   = '+33612340902';

    protected InMemoryOtpChallenges $challenges;
    protected InMemoryUsers $users;
    protected AccountFakes $fakes;
    protected PortalFakes $portal;
    protected \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->challenges = new InMemoryOtpChallenges();
        $this->users      = new InMemoryUsers();
        $this->fakes      = new AccountFakes();
        $this->portal     = new PortalFakes();
        $this->now        = new \DateTimeImmutable('2026-11-01 13:05:00', new \DateTimeZone('UTC'));
    }

    protected function owner(string $id, string $phone, ?string $email = null, bool $verified = false, UserStatus $status = UserStatus::ACTIVE): User
    {
        $user = User::reconstitute(UserId::fromString($id), $email, 'hash', $this->now, $status, $verified ? $this->now->modify('-1 day') : null, UserType::PORTAL, PhoneNumber::fromString($phone), $this->now);
        $this->users->save($user);

        return $user;
    }

    protected function requestActionCode(string $userId, string $purpose = 'email_change', string $code = '123456'): OtpChallengeIssued
    {
        $this->portal->nextCodes[] = $code;

        return $this->actionOtpHandler()(new RequestPortalActionOtp($userId, $purpose));
    }

    protected function actionOtpHandler(): RequestPortalActionOtpHandler
    {
        return new RequestPortalActionOtpHandler($this->challenges, $this->owners(), $this->portal, $this->portal, $this->fakes, $this->fakes, $this->clock());
    }

    protected function verifier(string $after = '+1 minute'): ActionOtpVerifier
    {
        return new ActionOtpVerifier($this->challenges, $this->portal, $this->clock($after));
    }

    protected function owners(): PortalOwnerAccounts
    {
        return new PortalOwnerAccounts($this->users);
    }

    protected function clock(string $after = '+0 seconds'): FrozenClock
    {
        return new FrozenClock($this->now->modify($after));
    }
}
