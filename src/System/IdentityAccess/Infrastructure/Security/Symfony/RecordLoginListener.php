<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Security\Symfony;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Command\RecordUserLogin\RecordUserLogin;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Every successful sign-in, whatever the firewall, stamps the account's last
 * login so the clinic team page can tell who has started using their access.
 */
#[AsEventListener(event: LoginSuccessEvent::class)]
final readonly class RecordLoginListener
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof SecurityUser) {
            return;
        }

        $this->commandBus->dispatch(new RecordUserLogin($user->id()));
    }
}
