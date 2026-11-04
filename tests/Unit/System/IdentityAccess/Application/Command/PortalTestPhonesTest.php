<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command;

use App\System\IdentityAccess\Application\Command\RequestPortalActionOtp\RequestPortalActionOtp;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\OtpChallengeIssued;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\RequestPortalOtp;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\RequestPortalOtpHandler;
use App\System\IdentityAccess\Application\Command\ResendPortalOtp\ResendPortalOtp;
use App\System\IdentityAccess\Application\Command\ResendPortalOtp\ResendPortalOtpHandler;
use App\System\IdentityAccess\Application\Command\VerifyPortalActionOtp\ActionOtpResult;
use App\System\IdentityAccess\Application\Exception\PhoneNotSupportedException;
use App\System\IdentityAccess\Application\Port\PendingPortalInvitation;
use App\System\IdentityAccess\Application\Service\UnknownNumberSmsGate;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use App\Tests\Unit\System\IdentityAccess\Support\PortalFakes;

/**
 * Test phones (PORTAL_TEST_PHONES): every code they request is the fixed
 * one and nothing is sent to them, whatever the purpose and the channel.
 */
final class PortalTestPhonesTest extends PortalActionOtpTestCase
{
    private const string TEST_PHONE = '+33639980001';
    private const string INVITATION = '01960000-0000-7000-8000-0000000009b1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->portal->testPhones = [self::TEST_PHONE, self::ALICE_PHONE];
    }

    public function testASignInCodeIsTheFixedCodeAndNothingIsSent(): void
    {
        $this->portal->budget = 0;

        $this->request(self::TEST_PHONE, OtpPurpose::LOGIN);
        $challenge = $this->challenges->single();

        self::assertSame([$this->portal->hash(PortalFakes::TEST_CODE), false], [$challenge->codeHash(), $challenge->isSmsSuppressed()]);
        self::assertSame([], $this->fakes->portalOtps, 'no SMS');
        self::assertSame([], $this->portal->budgetCalls, 'no unknown-number budget spent, even exhausted');
        self::assertSame([self::TEST_PHONE], $this->portal->policyChecks, 'the coverage still applies');
        self::assertSame([self::TEST_PHONE . ' login'], $this->portal->testPhoneRequests);
    }

    public function testTheCoverageStillAppliesToATestPhone(): void
    {
        $this->portal->mobile = false;

        try {
            $this->request(self::TEST_PHONE, OtpPurpose::LOGIN);
            self::fail('Refused.');
        } catch (PhoneNotSupportedException) {
            self::assertSame([[], []], [$this->portal->testPhoneRequests, $this->fakes->portalOtps]);
        }
    }

    public function testAnInvitationCodeIsTheFixedCodeAndNothingIsSent(): void
    {
        $this->portal->pending[self::TEST_PHONE] = [new PendingPortalInvitation(self::INVITATION, 'Clinique Montmartre', 'Paris')];

        $this->request(self::TEST_PHONE, OtpPurpose::INVITATION, self::INVITATION);

        self::assertSame($this->portal->hash(PortalFakes::TEST_CODE), $this->challenges->single()->codeHash());
        self::assertSame([[], [self::TEST_PHONE . ' invitation']], [$this->fakes->portalOtps, $this->portal->testPhoneRequests]);
    }

    public function testAResendGivesTheFixedCodeAgainOnEveryChannel(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE, 'alice@example.com', verified: true);
        $issued = $this->request(self::ALICE_PHONE, OtpPurpose::LOGIN);

        $this->resend($issued->challengeId, 'sms', '+35 seconds');
        $this->resend($issued->challengeId, 'email', '+70 seconds');

        $challenge = $this->challenges->single();
        self::assertSame([3, $this->portal->hash(PortalFakes::TEST_CODE)], [$challenge->sendCount(), $challenge->codeHash()]);
        self::assertSame([[], []], [$this->fakes->portalOtps, $this->fakes->emailedOtps], 'neither SMS nor e-mail');
        self::assertSame([], $this->portal->budgetCalls);
    }

    public function testAnActionCodeIsTheFixedCodeAndNothingIsSent(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE);

        $issued = $this->actionOtpHandler()(new RequestPortalActionOtp(self::ALICE, 'account_deletion'));

        self::assertSame([], $this->fakes->portalOtps);
        self::assertSame([self::ALICE_PHONE . ' account_deletion'], $this->portal->testPhoneRequests);
        self::assertSame(ActionOtpResult::VERIFIED, $this->verifier()->verify(self::ALICE, $issued->challengeId, PortalFakes::TEST_CODE, OtpPurpose::ACCOUNT_DELETION)->result);
    }

    public function testAnyOtherPhoneIsUnchanged(): void
    {
        $this->portal->nextCodes = ['654321'];

        $this->request(self::BOB_PHONE, OtpPurpose::LOGIN);

        self::assertSame($this->portal->hash('654321'), $this->challenges->single()->codeHash());
        self::assertCount(1, $this->fakes->portalOtps);
        self::assertSame(['global'], $this->portal->budgetCalls);
        self::assertSame([], $this->portal->testPhoneRequests);
    }

    private function request(string $phone, OtpPurpose $purpose, ?string $invitationRef = null): OtpChallengeIssued
    {
        $handler = new RequestPortalOtpHandler($this->challenges, $this->portal, $this->gate(), $this->portal, $this->portal, $this->fakes, $this->fakes, $this->clock(), $this->portal);

        return $handler(new RequestPortalOtp($phone, $purpose->value, $invitationRef));
    }

    private function resend(string $challengeId, string $channel, string $after): OtpChallengeIssued
    {
        $handler = new ResendPortalOtpHandler($this->challenges, $this->users, $this->portal, $this->portal, $this->fakes, $this->clock($after), $this->gate(), $this->portal);

        return $handler(new ResendPortalOtp($challengeId, $channel));
    }

    private function gate(): UnknownNumberSmsGate
    {
        return new UnknownNumberSmsGate($this->users, $this->portal, $this->portal);
    }
}
