<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;

/**
 * `status` = `authenticated` (token pair; `pendingInvitations` and an
 * `onboardingToken` when the phone has invitations waiting),
 * `invitation_pending` (no account yet, `invitations` waiting: accept them
 * with the `onboardingToken`), or `registration_required` (no account nor
 * invitation: `registrationToken`, 15 minutes, creates the account once on
 * `POST /auth/register`).
 * Fields of the other statuses are null or empty.
 */
final readonly class OtpVerificationView
{
    /**
     * @param list<PendingInvitationView> $pendingInvitations
     * @param list<PendingInvitationView> $invitations
     */
    public function __construct(
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['authenticated', 'invitation_pending', 'registration_required']])]
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
        #[ApiProperty(schema: ['type' => 'array', 'items' => PendingInvitationView::SCHEMA])]
        public array $invitations = [],
        #[ApiProperty(schema: ['type' => ['string', 'null'], 'format' => 'date-time'])]
        public ?string $registrationTokenExpiresAt = null,
    ) {
    }
}
