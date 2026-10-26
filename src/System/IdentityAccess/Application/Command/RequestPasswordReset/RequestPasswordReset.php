<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RequestPasswordReset;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Mot de passe oublié » on the clinic host. Routed to `async` and sent on
 * the raw `messenger.bus.command` (the CommandBusInterface expects a handled
 * result): the page answers the same way, in the same time, whether the
 * account exists or not. Only an ACTIVE clinic account receives a 30-minute
 * link.
 */
final readonly class RequestPasswordReset implements CommandInterface
{
    public function __construct(public string $email)
    {
    }
}
