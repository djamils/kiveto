<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command;

use App\System\IdentityAccess\Application\Command\StartPortalEmailChange\EmailChangeStarted;
use App\System\IdentityAccess\Application\Command\StartPortalEmailChange\StartPortalEmailChange;
use App\System\IdentityAccess\Application\Command\StartPortalEmailChange\StartPortalEmailChangeHandler;
use App\System\IdentityAccess\Application\Command\VerifyPortalActionOtp\ActionOtpResult;
use App\System\IdentityAccess\Application\Exception\PortalAccountUnavailableException;
use App\System\IdentityAccess\Application\Port\PortalEmailVerificationRequest;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpChannel;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;

/** AC 61, AC 206: the SMS code of the user opens the verification of the new address. */
final class StartPortalEmailChangeHandlerTest extends PortalActionOtpTestCase
{
    public function testTheSmsCodeSendsACodeToTheNewAddress(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE, 'old@example.com', verified: true);
        $sms                       = $this->requestActionCode(self::ALICE);
        $this->portal->nextCodes[] = '777777';

        $started = $this->start(self::ALICE, $sms->challengeId, '123456', '  New.Address@Example.COM ');

        self::assertSame(ActionOtpResult::VERIFIED, $started->result);
        self::assertNotNull($started->verificationChallengeId);
        $challenge = $this->challenges->findById(OtpChallengeId::fromString($started->verificationChallengeId));
        self::assertNotNull($challenge);
        self::assertSame([OtpPurpose::EMAIL_VERIFICATION, self::ALICE, 'new.address@example.com', OtpChannel::EMAIL, self::ALICE_PHONE, '13:16:00'], [
            $challenge->purpose(), $challenge->subjectRef(), $challenge->targetEmail(), $challenge->lastChannel(), $challenge->phone()->toString(), $started->expiresAt?->format('H:i:s'),
        ]);
        self::assertSame([[$started->verificationChallengeId, self::ALICE, 'new.address@example.com', '777777', 10]], array_map(
            static fn (PortalEmailVerificationRequest $r): array => [$r->challengeId, $r->userId, $r->email, $r->code, $r->validityMinutes],
            $this->fakes->emailVerifications,
        ));
        self::assertSame('old@example.com', $this->users->users[self::ALICE]->email(), 'nothing changes before the new address is proven');
    }

    public function testAWrongOrForeignCodeSendsNothing(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE);
        $this->owner(self::BOB, self::BOB_PHONE);
        $sms = $this->requestActionCode(self::ALICE);

        self::assertSame(ActionOtpResult::OTP_INVALID, $this->start(self::BOB, $sms->challengeId, '123456', 'bob@example.com')->result, 'the code of Alice for Bob');
        self::assertSame(ActionOtpResult::OTP_INVALID, $this->start(self::ALICE, $sms->challengeId, '000000', 'alice@example.com')->result);
        self::assertSame(ActionOtpResult::OTP_EXPIRED, $this->start(self::ALICE, $sms->challengeId, '123456', 'alice@example.com', '+11 minutes')->result);

        $deletion = $this->requestActionCode(self::ALICE, 'account_deletion');
        self::assertSame(ActionOtpResult::OTP_INVALID, $this->start(self::ALICE, $deletion->challengeId, '123456', 'alice@example.com')->result, 'a deletion code');

        self::assertSame([], $this->fakes->emailVerifications);
        self::assertCount(2, $this->challenges->challenges, 'no verification challenge');
    }

    public function testAnInvalidAddressIsRefusedBeforeTheCodeIsSpent(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE);
        $sms = $this->requestActionCode(self::ALICE);

        foreach (['.alice@example.com', str_repeat('a', 170) . '@example.com'] as $email) {
            try {
                $this->start(self::ALICE, $sms->challengeId, '123456', $email);
                self::fail($email);
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('The e-mail address is not valid.', $exception->getMessage());
            }
        }

        self::assertSame(0, $this->challenges->single()->attempts());
    }

    public function testADisabledAccountStartsNothing(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE);
        $sms = $this->requestActionCode(self::ALICE);
        $this->owner(self::ALICE, self::ALICE_PHONE, status: UserStatus::DISABLED);

        $this->expectException(PortalAccountUnavailableException::class);
        $this->start(self::ALICE, $sms->challengeId, '123456', 'alice@example.com');
    }

    private function start(string $userId, string $challengeId, string $code, string $email, string $after = '+1 minute'): EmailChangeStarted
    {
        $handler = new StartPortalEmailChangeHandler($this->verifier($after), $this->owners(), $this->challenges, $this->portal, $this->portal, $this->fakes, $this->fakes, $this->clock($after));

        return $handler(new StartPortalEmailChange($userId, $challengeId, $code, $email));
    }
}
