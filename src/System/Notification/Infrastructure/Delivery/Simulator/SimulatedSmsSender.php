<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Delivery\Simulator;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\System\Notification\Application\Port\OutgoingSms;
use App\System\Notification\Application\Port\SmsSenderInterface;
use App\System\Notification\Domain\Message\Channel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * An SMS never leaves the machine: its text is logged on `external_api`
 * (it stays readable on the message itself) and its delivery webhook faked.
 */
final readonly class SimulatedSmsSender implements SmsSenderInterface
{
    public const string ID_PREFIX = 'sim-sms-';

    public function __construct(
        private SimulatedDeliveryScheduler $scheduler,
        private UuidGeneratorInterface $uuids,
        #[Autowire(service: 'monolog.logger.external_api')]
        private LoggerInterface $logger,
    ) {
    }

    public function send(OutgoingSms $sms): string
    {
        $externalId = self::ID_PREFIX . $this->uuids->generate();

        $this->logger->info('Simulated SMS.', ['externalId' => $externalId, 'from' => $sms->from, 'to' => $sms->to, 'text' => $sms->text, 'tag' => $sms->tag]);
        $this->scheduler->schedule(Channel::SMS, $externalId, $sms->to);

        return $externalId;
    }
}
