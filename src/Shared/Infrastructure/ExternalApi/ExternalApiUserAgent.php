<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\ExternalApi;

/**
 * The User-Agent sent to external services (EXTERNAL_API_USER_AGENT).
 *
 * Public APIs ask callers to identify themselves with a contact. The contact is
 * checked when an adapter that really calls out is built, not at boot, so that
 * cache:clear, migrations and the fixture adapters never depend on it.
 */
final readonly class ExternalApiUserAgent
{
    public function __construct(private string $userAgent)
    {
    }

    /**
     * @throws \LogicException when the value holds neither an e-mail address nor an http(s) URL
     */
    public function withContact(string $service): string
    {
        $hasEmail = 1 === preg_match('/[^\s@<>()+]+@[^\s@<>()]+\.[a-z]{2,}/i', $this->userAgent);
        $hasUrl   = 1 === preg_match('#https?://[^\s/]+\.[^\s/]+#i', $this->userAgent);

        if (!$hasEmail && !$hasUrl) {
            throw new \LogicException(\sprintf('EXTERNAL_API_USER_AGENT must contain a contact (e-mail or URL) to call %s', $service));
        }

        return $this->userAgent;
    }
}
