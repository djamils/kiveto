<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command;

use App\System\IdentityAccess\Application\Command\RequestPortalOtp\RequestPortalOtp;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\RequestPortalOtpHandler;
use App\System\IdentityAccess\Application\Command\VerifyPortalActionOtp\ActionOtpResult;
use App\System\IdentityAccess\Application\Command\VerifyPortalActionOtp\VerifyPortalActionOtp;
use App\System\IdentityAccess\Application\Command\VerifyPortalActionOtp\VerifyPortalActionOtpHandler;
use App\System\IdentityAccess\Application\Exception\PortalAccountUnavailableException;
use App\System\IdentityAccess\Application\Port\PortalOtpRequest;
use App\System\IdentityAccess\Application\Service\UnknownNumberSmsGate;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use App\System\IdentityAccess\Domain\Otp\OtpSubjectType;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;

/** RequestPortalActionOtp and VerifyPortalActionOtp, reused by the account deletion (Task 37). */
final class PortalActionOtpHandlersTest extends PortalActionOtpTestCase
{
    public function testAnActionCodeGoesToTheAccountPhoneWithItsWording(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE);
        $this->portal->mobile = false;
        $this->portal->budget = 0;

        $issued    = $this->requestActionCode(self::ALICE, 'account_deletion', '654321');
        $challenge = $this->challenges->single();

        self::assertSame([$challenge->id()->toString(), '13:15:00', '13:05:30'], [$issued->challengeId, $issued->expiresAt->format('H:i:s'), $issued->resendAvailableAt->format('H:i:s')]);
        self::assertSame([self::ALICE_PHONE, OtpPurpose::ACCOUNT_DELETION, OtpSubjectType::USER, self::ALICE], [$challenge->phone()->toString(), $challenge->purpose(), $challenge->subjectType(), $challenge->subjectRef()]);
        self::assertSame([[], 0], [$this->portal->policyChecks, $this->portal->budgetConsumptions], 'a proven phone: no coverage check, no unknown-number budget');
        self::assertSame([[$challenge->id()->toString(), self::ALICE_PHONE, '654321', 1, 10, 'account_deletion']], array_map(
            static fn (PortalOtpRequest $r): array => [$r->challengeId, $r->phone, $r->code, $r->sendCount, $r->validityMinutes, $r->purpose],
            $this->fakes->portalOtps,
        ));
    }

    public function testOnlyActionsAreConfirmedByAnActionCode(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE);

        foreach (['login', 'invitation', 'email_verification'] as $purpose) {
            try {
                $this->requestActionCode(self::ALICE, $purpose);
                self::fail($purpose);
            } catch (\InvalidArgumentException $exception) {
                self::assertSame(\sprintf('"%s" is not an action confirmed by an SMS code.', $purpose), $exception->getMessage());
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->verifyHandler()(new VerifyPortalActionOtp(self::ALICE, '01960000-0000-7000-8000-0000000009c1', '123456', 'login'));
    }

    public function testASignInCodeIsNeverAnActionCode(): void
    {
        $handler = new RequestPortalOtpHandler($this->challenges, $this->portal, new UnknownNumberSmsGate($this->users, $this->portal, $this->portal), $this->portal, $this->portal, $this->fakes, $this->fakes, $this->clock(), $this->portal);

        $this->expectExceptionMessage('Action codes are requested by RequestPortalActionOtp, for a user.');
        $handler(new RequestPortalOtp(self::ALICE_PHONE, 'email_change'));
    }

    public function testOnlyAnActiveOwnerWithAPhoneGetsAnActionCode(): void
    {
        $this->owner(self::BOB, self::BOB_PHONE, status: UserStatus::DISABLED);

        foreach ([self::BOB, self::ALICE, 'not-a-uuid'] as $userId) {
            try {
                $this->requestActionCode($userId);
                self::fail($userId);
            } catch (PortalAccountUnavailableException $exception) {
                self::assertSame('Ce compte n\'est plus disponible.', $exception->getMessage());
            }
        }

        self::assertSame([[], []], [$this->challenges->challenges, $this->fakes->portalOtps]);
    }

    public function testTheCodeConfirmsTheActionOfItsUserOnce(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE);
        $this->owner(self::BOB, self::BOB_PHONE);
        $issued = $this->requestActionCode(self::ALICE, 'account_deletion');

        self::assertSame(ActionOtpResult::OTP_INVALID, $this->verify(self::BOB, $issued->challengeId, '123456', 'account_deletion'), 'the code of Alice for Bob');
        self::assertSame(ActionOtpResult::OTP_INVALID, $this->verify(self::ALICE, $issued->challengeId, '123456', 'email_change'), 'another action');
        self::assertSame(ActionOtpResult::VERIFIED, $this->verify(self::ALICE, $issued->challengeId, '123456', 'account_deletion'));
        self::assertSame(ActionOtpResult::OTP_INVALID, $this->verify(self::ALICE, $issued->challengeId, '123456', 'account_deletion'), 'used once, no replay');
        self::assertSame(2, $this->challenges->single()->attempts(), 'the wrong attempts are saved (a replay of the right code is not one)');

        self::assertSame(ActionOtpResult::OTP_INVALID, $this->verify(self::ALICE, '01960000-0000-7000-8000-0000000009ff', '123456', 'account_deletion'));
        self::assertSame(ActionOtpResult::OTP_INVALID, $this->verify(self::ALICE, 'not-a-uuid', '123456', 'account_deletion'));
    }

    public function testAnActionCodeExpiresAndCountsItsAttempts(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE);
        $expired = $this->requestActionCode(self::ALICE);
        self::assertSame(ActionOtpResult::OTP_EXPIRED, $this->verify(self::ALICE, $expired->challengeId, '123456', 'email_change', '+10 minutes'));

        $this->challenges->challenges = [];
        $issued                       = $this->requestActionCode(self::ALICE);
        for ($attempt = 1; $attempt <= 4; ++$attempt) {
            self::assertSame(ActionOtpResult::OTP_INVALID, $this->verify(self::ALICE, $issued->challengeId, '000000', 'email_change'));
        }
        self::assertSame(ActionOtpResult::OTP_ATTEMPTS_EXCEEDED, $this->verify(self::ALICE, $issued->challengeId, '000000', 'email_change'));
        self::assertSame(ActionOtpResult::OTP_ATTEMPTS_EXCEEDED, $this->verify(self::ALICE, $issued->challengeId, '123456', 'email_change'));
    }

    private function verify(string $userId, string $challengeId, string $code, string $purpose, string $after = '+1 minute'): ActionOtpResult
    {
        return $this->verifyHandler($after)(new VerifyPortalActionOtp($userId, $challengeId, $code, $purpose));
    }

    private function verifyHandler(string $after = '+1 minute'): VerifyPortalActionOtpHandler
    {
        return new VerifyPortalActionOtpHandler($this->verifier($after));
    }
}
