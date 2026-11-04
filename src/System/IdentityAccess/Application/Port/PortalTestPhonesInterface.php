<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;

/**
 * Test phone numbers (PORTAL_TEST_PHONES): the testers of STAGING and DEMO,
 * the device tests of the owner app, and the store review account in
 * production. Every sign-in, invitation and action code of such a phone is
 * the fixed code (PORTAL_TEST_OTP_CODE) and is never delivered: no SMS, no
 * e-mail, no unknown-number budget spent. The API's quotas and the attempt
 * limits of the challenge still apply.
 */
interface PortalTestPhonesInterface
{
    /** The fixed code when `$phone` is a test phone (the request is logged), else null. */
    public function fixedCodeFor(PhoneNumber $phone, OtpPurpose $purpose): ?string;
}
