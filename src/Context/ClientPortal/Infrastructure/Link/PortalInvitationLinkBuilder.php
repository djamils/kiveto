<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Link;

use App\Context\ClientPortal\Application\Port\InvitationLinkBuilderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * `<PORTAL_PUBLIC_BASE_URL>/invitation#t=<token>`: the token sits in the
 * fragment, which browsers never send to the server, while the OS hands the
 * whole URL to the app (universal / app link).
 */
final readonly class PortalInvitationLinkBuilder implements InvitationLinkBuilderInterface
{
    private string $baseUrl;

    public function __construct(#[Autowire('%env(PORTAL_PUBLIC_BASE_URL)%')] string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function invitationLink(#[\SensitiveParameter] string $token): string
    {
        return $this->baseUrl . '/invitation#t=' . rawurlencode($token);
    }
}
