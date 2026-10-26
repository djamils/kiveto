<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\DispatchOutboundMessage;

use App\Shared\Application\Bus\ExternalApiCommandInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * « Hand this message to the e-mail or SMS provider »: network only; the
 * outcome is recorded by a short transactional command.
 *
 * It waits on its own transport, `async_external_notification` (own retry
 * policy): send it with `stamps()`. A YAML route would not do — Messenger
 * adds the senders of every matching type, so the generic
 * ExternalApiCommandInterface route would queue it on `async_external` too.
 */
final readonly class DispatchOutboundMessage implements ExternalApiCommandInterface
{
    public const string TRANSPORT = 'async_external_notification';

    public function __construct(public string $messageId)
    {
    }

    /**
     * @return list<TransportNamesStamp>
     */
    public static function stamps(): array
    {
        return [new TransportNamesStamp([self::TRANSPORT])];
    }
}
