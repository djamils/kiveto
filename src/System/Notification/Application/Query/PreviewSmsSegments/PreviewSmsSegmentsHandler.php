<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Query\PreviewSmsSegments;

use App\System\Notification\Application\Exception\InvalidNotificationRequestException;
use App\System\Notification\Application\Port\MessageRendererInterface;
use App\System\Notification\Domain\Clinic\ClinicMessagingProfileRepositoryInterface;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\MessagePurpose;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PreviewSmsSegmentsHandler
{
    public function __construct(
        private MessageRendererInterface $renderer,
        private ClinicMessagingProfileRepositoryInterface $profiles,
    ) {
    }

    /**
     * @throws InvalidNotificationRequestException unknown purpose, purpose without SMS, wrong parameters
     */
    public function __invoke(PreviewSmsSegments $query): int
    {
        $purpose = MessagePurpose::tryFrom($query->purpose) ?? throw new InvalidNotificationRequestException(\sprintf('Unknown purpose "%s".', $query->purpose));
        $profile = $this->profiles->find($query->clinicId);

        if (null === $profile && !$purpose->isSentByKiveto()) {
            return 0;
        }

        return $this->renderer->render($purpose, Channel::SMS, $query->parameters, $profile)->segments * max(0, $query->count);
    }
}
