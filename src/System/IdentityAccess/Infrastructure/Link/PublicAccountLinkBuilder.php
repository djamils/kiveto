<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Link;

use App\System\IdentityAccess\Application\Port\AccountLinkBuilderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Links on the clinic host (KIVETO_PUBLIC_BASE_URL); the pages store the
 * secret in the session and redirect to a URL without it.
 */
final readonly class PublicAccountLinkBuilder implements AccountLinkBuilderInterface
{
    private string $baseUrl;

    public function __construct(#[Autowire('%env(KIVETO_PUBLIC_BASE_URL)%')] string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function invitationUrl(string $secret): string
    {
        return $this->baseUrl . '/invitation/' . rawurlencode($secret);
    }

    public function passwordResetUrl(string $secret): string
    {
        return $this->baseUrl . '/reinitialiser/' . rawurlencode($secret);
    }
}
