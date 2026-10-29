<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Presentation\Api\Portal\State\VerifyOtpProcessor;
use App\Presentation\Api\Portal\View\OtpVerificationView;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'OtpVerification',
    operations: [
        new Post(
            uriTemplate: '/auth/otp/verify',
            status: 200,
            openapi: new Operation(
                tags: ['Authentification'],
                summary: 'Vérifie un code de connexion.',
                responses: [
                    '200' => new Response(description: '`status` = `authenticated` (paire de jetons, invitations en attente), `invitation_pending` (jeton d\'onboarding, invitations) ou `registration_required`.'),
                    '403' => new Response(description: '`account_disabled`.'),
                    '422' => new Response(description: '`otp_invalid`, `otp_expired`, `otp_attempts_exceeded` ou `validation_failed`.'),
                ],
            ),
            output: OtpVerificationView::class,
            processor: VerifyOtpProcessor::class,
        ),
    ],
)]
final class OtpVerification
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $challengeId;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{6}$/', message: 'Le code comporte 6 chiffres.')]
    public string $code;
}
