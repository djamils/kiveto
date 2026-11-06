<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Link;

use App\System\IdentityAccess\Application\Port\AccountLinkBuilderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Links on the clinic host (KIVETO_PUBLIC_BASE_URL), and on the backoffice
 * host of the same scheme for the Kiveto team; the pages store the secret in
 * the session and redirect to a URL without it.
 */
final readonly class PublicAccountLinkBuilder implements AccountLinkBuilderInterface
{
    private string $baseUrl;

    private string $backofficeBaseUrl;

    public function __construct(
        #[Autowire('%env(KIVETO_PUBLIC_BASE_URL)%')]
        string $baseUrl,
        #[Autowire('%env(kiveto_domain:KIVETO_DOMAIN)%')]
        string $domain,
    ) {
        $this->baseUrl           = rtrim($baseUrl, '/');
        $this->backofficeBaseUrl = (parse_url($baseUrl, \PHP_URL_SCHEME) ?? 'https') . '://backoffice.' . $domain;
    }

    public function invitationUrl(string $secret): string
    {
        return $this->baseUrl . '/invitation/' . rawurlencode($secret);
    }

    public function passwordResetUrl(string $secret): string
    {
        return $this->baseUrl . '/reinitialiser/' . rawurlencode($secret);
    }

    public function backofficePasswordResetUrl(string $secret): string
    {
        return $this->backofficeBaseUrl . '/reinitialiser/' . rawurlencode($secret);
    }

    public function supportHandoffUrl(string $secret): string
    {
        return $this->baseUrl . '/support/' . rawurlencode($secret);
    }
}
