<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Persistence\Doctrine\Repository;

use App\System\Notification\Domain\Exception\OutboundMessageNotFoundException;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\DeliveryStatus;
use App\System\Notification\Domain\Message\IdempotencyKey;
use App\System\Notification\Domain\Message\OutboundMessage;
use App\System\Notification\Domain\Message\OutboundMessageId;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use App\System\Notification\Infrastructure\Persistence\Doctrine\Entity\OutboundMessageEntity;
use App\System\Notification\Infrastructure\Persistence\Doctrine\Mapper\OutboundMessageMapper;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

/**
 * Two writers never overwrite each other: the version column is an
 * optimistic lock, a concurrent change fails the flush and Messenger retries
 * the message. Two first requests with the same idempotency key collide on
 * its unique index: the loser is retried and finds the winner's message.
 *
 * The SMS sums are locking reads (`FOR SHARE`): the cap check runs under the
 * clinic profile row lock inside a REPEATABLE READ transaction whose snapshot
 * may predate the previous request's commit; a locking read always sees the
 * latest committed rows.
 */
final readonly class DoctrineOutboundMessageRepository implements OutboundMessageRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private OutboundMessageMapper $mapper,
    ) {
    }

    public function save(OutboundMessage $message): void
    {
        $existing = $this->em->find(OutboundMessageEntity::class, Uuid::fromString($message->id()->toString()));

        if (null === $existing) {
            $this->em->persist($this->mapper->toEntity($message));
        } else {
            $this->mapper->toEntity($message, $existing);
        }

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException $e) {
            if (null !== $existing) {
                throw $e;
            }

            throw new RecoverableMessageHandlingException(\sprintf('Message %s requested concurrently.', $message->idempotencyKey()->toString()), 0, $e);
        }
    }

    public function get(OutboundMessageId $id): OutboundMessage
    {
        $entity = $this->em->find(OutboundMessageEntity::class, Uuid::fromString($id->toString()));

        if (null === $entity) {
            throw new OutboundMessageNotFoundException($id->toString());
        }

        return $this->mapper->toDomain($entity);
    }

    public function findByIdempotencyKey(IdempotencyKey $key): ?OutboundMessage
    {
        return $this->findOne(['idempotencyKey' => $key->toString()]);
    }

    public function findByExternalId(Channel $channel, string $externalMessageId): ?OutboundMessage
    {
        return $this->findOne(['channel' => $channel->value, 'externalMessageId' => $externalMessageId]);
    }

    public function sumBilledSegments(string $clinicId, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return $this->sum(
            'sent_at >= :from AND sent_at < :to',
            $clinicId,
            ['from' => OutboundMessageMapper::utc($from)->format('Y-m-d H:i:s'), 'to' => OutboundMessageMapper::utc($to)->format('Y-m-d H:i:s')],
        );
    }

    public function sumQueuedSegments(string $clinicId): int
    {
        return $this->sum('status = :status', $clinicId, ['status' => DeliveryStatus::QUEUED->value]);
    }

    public function findContentToPurge(\DateTimeImmutable $before, int $limit): array
    {
        $ids = $this->em->getConnection()->fetchFirstColumn(
            'SELECT id FROM notification__outbound_messages WHERE content_purged_at IS NULL AND COALESCE(sent_at, created_at) < :before'
            . ' ORDER BY COALESCE(sent_at, created_at), id LIMIT ' . max(1, $limit),
            ['before' => OutboundMessageMapper::utc($before)->format('Y-m-d H:i:s')],
        );

        return array_map(static fn (mixed $id): OutboundMessageId => OutboundMessageId::fromString(Uuid::fromBinary(\is_string($id) ? $id : '')->toRfc4122()), $ids);
    }

    /**
     * @param array<string, string> $criteria
     */
    private function findOne(array $criteria): ?OutboundMessage
    {
        $entity = $this->em->getRepository(OutboundMessageEntity::class)->findOneBy($criteria);

        return null === $entity ? null : $this->mapper->toDomain($entity);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function sum(string $condition, string $clinicId, array $parameters): int
    {
        $sum = $this->em->getConnection()->fetchOne(
            'SELECT COALESCE(SUM(segments), 0) FROM notification__outbound_messages'
            . ' WHERE clinic_id = :clinic AND channel = :channel AND ' . $condition . ' FOR SHARE',
            ['clinic' => Uuid::fromString($clinicId)->toBinary(), 'channel' => Channel::SMS->value, ...$parameters],
        );

        return is_numeric($sum) ? (int) $sum : 0;
    }
}
