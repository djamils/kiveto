<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;

/**
 * `status` = `authenticated` (token pair, pending invitations of the phone),
 * or `registration_required` (no account for this phone; `registrationToken`
 * stays null until free sign-up exists). Fields of the other status are null.
 */
final readonly class OtpVerificationView
{
    /**
     * @param list<PendingInvitationView> $pendingInvitations
     */
    public function __construct(
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['authenticated', 'registration_required']])]
        public string $status,
        public ?string $accessToken = null,
        #[ApiProperty(schema: ['type' => ['string', 'null'], 'format' => 'date-time'])]
        public ?string $accessTokenExpiresAt = null,
        public ?string $refreshToken = null,
        #[ApiProperty(schema: ['type' => ['string', 'null'], 'format' => 'date-time'])]
        public ?string $refreshTokenExpiresAt = null,
        #[ApiProperty(schema: ['type' => 'array', 'items' => PendingInvitationView::SCHEMA])]
        public array $pendingInvitations = [],
        public ?string $onboardingToken = null,
        #[ApiProperty(schema: ['type' => ['string', 'null'], 'format' => 'date-time'])]
        public ?string $onboardingTokenExpiresAt = null,
        public ?string $registrationToken = null,
    ) {
    }
}
