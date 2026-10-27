<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Webhook;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IpsRequestMatcher;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * Brevo does not sign its webhooks: a call is accepted only when it comes
 * from Brevo's published ranges (client IP resolved through the trusted
 * proxies) and carries the shared secret — as an `Authorization: Bearer`
 * header when present, else as the `token` query parameter.
 *
 * NOTIFICATION_WEBHOOK_SKIP_IP_CHECK=1 (dev tunnel, which hides Brevo's IP)
 * disables the IP check only, and is refused in production. So is the
 * placeholder secret of `.env` (or none): Brevo's ranges are shared by every
 * Brevo customer, so the secret is what tells our account's calls apart.
 */
final readonly class BrevoRequestGuard
{
    /** The placeholder committed in `.env`, never a real secret. */
    public const string PLACEHOLDER_SECRET = 'change-me';

    private IpsRequestMatcher $brevoIps;

    /**
     * @param list<string> $allowedIps
     */
    public function __construct(
        #[Autowire(param: 'notification.brevo.webhook_ips')]
        array $allowedIps,
        #[Autowire(env: 'BREVO_WEBHOOK_TOKEN')]
        #[\SensitiveParameter]
        private string $secret,
        #[Autowire(env: 'bool:NOTIFICATION_WEBHOOK_SKIP_IP_CHECK')]
        private bool $skipIpCheck,
        #[Autowire(param: 'kernel.environment')]
        string $environment,
    ) {
        if ($skipIpCheck && 'prod' === $environment) {
            throw new \LogicException('NOTIFICATION_WEBHOOK_SKIP_IP_CHECK must not be set in production.');
        }

        if ('prod' === $environment && \in_array($secret, ['', self::PLACEHOLDER_SECRET], true)) {
            throw new \LogicException('BREVO_WEBHOOK_TOKEN must be set to a real secret in production.');
        }

        $this->brevoIps = new IpsRequestMatcher($allowedIps);
    }

    /**
     * @throws RejectWebhookException when the call does not come from Brevo or lacks the secret
     */
    public function check(Request $request): void
    {
        if (!$this->skipIpCheck && !$this->brevoIps->matches($request)) {
            throw new RejectWebhookException(403, 'Request does not come from Brevo.');
        }

        if ('' === $this->secret || !hash_equals($this->secret, $this->presentedSecret($request))) {
            throw new RejectWebhookException(401, 'Invalid credentials.');
        }
    }

    private function presentedSecret(Request $request): string
    {
        $authorization = (string) $request->headers->get('Authorization', '');
        if (str_starts_with($authorization, 'Bearer ')) {
            return substr($authorization, 7);
        }

        $token = $request->query->all()['token'] ?? '';

        return \is_string($token) ? $token : '';
    }
}
