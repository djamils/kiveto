<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Delivery;

use App\System\Notification\Domain\Message\Channel;

interface UnmatchedDeliveryEventRepositoryInterface
{
    public function keep(UnmatchedDeliveryEvent $event): void;

    /**
     * @return list<UnmatchedDeliveryEvent> the events kept for that provider id, oldest first
     */
    public function findFor(Channel $channel, string $externalId): array;

    /**
     * @return list<UnmatchedDeliveryEvent> the events whose provider id is now recorded on a message, oldest first
     */
    public function findMatchable(int $limit): array;

    /**
     * @return list<UnmatchedDeliveryEvent> the events received before $before, oldest first
     */
    public function findReceivedBefore(\DateTimeImmutable $before): array;

    public function delete(string $id): void;
}
