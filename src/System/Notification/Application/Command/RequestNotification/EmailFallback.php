<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\RequestNotification;

/**
 * The e-mail to send instead of a non-critical SMS held back by the overage
 * cap — same purpose, e-mail template. The requester passes it only when the
 * client may receive that e-mail (consent rule of the purpose).
 */
final readonly class EmailFallback
{
    /**
     * @param array<string, scalar|list<scalar>> $parameters the parameters of the purpose's e-mail template
     */
    public function __construct(
        public string $address,
        public string $name,
        public array $parameters,
    ) {
    }
}
