<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;

/**
 * The test phone of `.env.test` (PORTAL_TEST_PHONES, PORTAL_TEST_OTP_CODE):
 * its owner signs in with the fixed code, and no SMS is requested.
 */
final class TestPhoneSignInTest extends PortalApiTestCase
{
    private const string TEST_PHONE = '+33639980001';
    private const string TEST_CODE  = '424242';

    public function testTheOwnerOfATestPhoneSignsInWithTheFixedCode(): void
    {
        PortalUserFactory::new()->withPhone(self::TEST_PHONE)->create();

        $challengeId = $this->requestCode(self::TEST_PHONE);

        self::assertSame(0, $this->otpSmsCount(), 'no SMS is requested for a test phone');
        self::assertSame('otp_invalid', self::problemType($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => '000000']), 422), 'only the fixed code works');

        $answer = self::json($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => self::TEST_CODE]), 200);
        self::assertSame('authenticated', $answer['status']);
    }
}
