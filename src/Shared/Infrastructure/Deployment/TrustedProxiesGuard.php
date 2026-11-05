<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Deployment;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Refuses to serve a request when a deployment other than dev does not name
 * its reverse proxy (SYMFONY_TRUSTED_PROXIES, `REMOTE_ADDR` in .env.prod).
 * Without it, every request would look like a plain HTTP one coming from the
 * edge Nginx: session cookies without `Secure`, and every per-IP limit
 * (sign-in, one-time codes, forgotten password, Brevo's ranges) shared by
 * everyone. Trusting every address is refused too: X-Forwarded-For would be
 * the client's to choose. Checked on each request, before the router (32),
 * so a wrong value fails the healthchecks of the deployment.
 */
final readonly class TrustedProxiesGuard implements EventSubscriberInterface
{
    private const array EVERY_ADDRESS = ['0.0.0.0/0', '::/0'];

    public function __construct(
        #[Autowire(env: 'enum:App\Shared\Infrastructure\Deployment\Deployment:KIVETO_DEPLOYMENT')]
        private Deployment $deployment,
        #[Autowire(env: 'SYMFONY_TRUSTED_PROXIES')]
        private string $trustedProxies,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 256],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (Deployment::DEV === $this->deployment || !$event->isMainRequest()) {
            return;
        }

        $proxies = array_values(array_filter(array_map(trim(...), explode(',', $this->trustedProxies))));

        if ([] === $proxies) {
            throw new \LogicException(\sprintf('SYMFONY_TRUSTED_PROXIES must name the reverse proxy on the %s deployment.', $this->deployment->value));
        }

        if ([] !== array_intersect($proxies, self::EVERY_ADDRESS)) {
            throw new \LogicException(\sprintf('SYMFONY_TRUSTED_PROXIES must not trust every address on the %s deployment.', $this->deployment->value));
        }
    }
}
