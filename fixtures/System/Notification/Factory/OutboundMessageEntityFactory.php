<?php

declare(strict_types=1);

namespace App\Fixtures\System\Notification\Factory;

use App\System\Notification\Infrastructure\Persistence\Doctrine\Entity\OutboundMessageEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * A journal row written directly, without the pipeline: by default an SMS
 * appointment reminder handed to the provider and delivered — the volume a
 * clinic has already sent this month, which the SMS quota counts.
 *
 * @extends PersistentObjectFactory<OutboundMessageEntity>
 */
final class OutboundMessageEntityFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return OutboundMessageEntity::class;
    }

    /**
     * A delivered SMS of the clinic, handed over at the given time.
     */
    public function deliveredSms(string $clinicId, string $smsSender, \DateTimeImmutable $sentAt, int $segments): self
    {
        $id          = Uuid::v7();
        $sentAt      = $sentAt->setTimezone(new \DateTimeZone('UTC'));
        $deliveredAt = $sentAt->modify('+4 seconds');
        $text        = \sprintf('%s : rappel de votre RDV. Pour le modifier ou l\'annuler, appelez la clinique.', $smsSender);

        return $this->with([
            'id'                => $id,
            'clinicId'          => Uuid::fromString($clinicId),
            'channel'           => 'SMS',
            'purpose'           => 'APPOINTMENT_REMINDER',
            'criticality'       => 'NON_CRITICAL',
            'recipientAddress'  => \sprintf('+336%08d', self::faker()->numberBetween(10000000, 99999999)),
            'recipientName'     => self::faker()->name(),
            'sender'            => ['name' => $smsSender, 'email' => '', 'replyTo' => null, 'sms' => $smsSender],
            'subjectType'       => 'appointment',
            'subjectId'         => Uuid::v7()->toRfc4122(),
            'idempotencyKey'    => 'fixture:sms-volume:' . $id->toRfc4122(),
            'content'           => ['subject' => null, 'html' => null, 'text' => null, 'inlineLogoKey' => null, 'smsText' => $text, 'encoding' => 'GSM-7', 'segments' => $segments],
            'segments'          => $segments,
            'status'            => 'DELIVERED',
            'externalMessageId' => (string) self::faker()->numberBetween(100000000, 999999999),
            'statusHistory'     => [
                ['status' => 'QUEUED', 'occurredAt' => $sentAt->modify('-1 second')->format(\DATE_ATOM), 'source' => 'request', 'reason' => null],
                ['status' => 'SENT', 'occurredAt' => $sentAt->format(\DATE_ATOM), 'source' => 'dispatch', 'reason' => null],
                ['status' => 'DELIVERED', 'occurredAt' => $deliveredAt->format(\DATE_ATOM), 'source' => 'provider', 'reason' => null],
            ],
            'createdAt'       => $sentAt->modify('-1 second'),
            'sentAt'          => $sentAt,
            'statusChangedAt' => $deliveredAt,
        ]);
    }

    /**
     * A message to a client, at whatever stage its history ends: the client
     * journal mirrors it (the story records each status there).
     *
     * @param non-empty-list<array{status: string, at: \DateTimeImmutable, source: string, reason: ?string}> $history oldest first
     */
    public function clientMessage(string $clinicId, string $clientId, string $channel, string $purpose, string $address, string $name, string $subject, array $history): self
    {
        $id   = Uuid::v7();
        $utc  = new \DateTimeZone('UTC');
        $sent = array_values(array_filter($history, static fn (array $change): bool => 'SENT' === $change['status']))[0]['at'] ?? null;
        $last = $history[\count($history) - 1];
        $sms  = 'SMS' === $channel;

        return $this->with([
            'id'               => $id,
            'clinicId'         => Uuid::fromString($clinicId),
            'clientId'         => Uuid::fromString($clientId),
            'channel'          => $channel,
            'purpose'          => $purpose,
            'criticality'      => 'APPOINTMENT_REMINDER' === $purpose ? 'NON_CRITICAL' : 'CRITICAL',
            'recipientAddress' => $address,
            'recipientName'    => $name,
            'sender'           => ['name' => 'Clinique', 'email' => '', 'replyTo' => null, 'sms' => 'Kiveto'],
            'subjectType'      => 'fixture',
            'subjectId'        => $id->toRfc4122(),
            'idempotencyKey'   => 'fixture:journal:' . $id->toRfc4122(),
            'content'          => $sms
                ? ['subject' => null, 'html' => null, 'text' => null, 'inlineLogoKey' => null, 'smsText' => $subject, 'encoding' => 'GSM-7', 'segments' => 1]
                : ['subject' => $subject, 'html' => '<p>' . htmlspecialchars($subject) . '</p>', 'text' => $subject, 'inlineLogoKey' => null, 'smsText' => null, 'encoding' => null, 'segments' => 0],
            'segments'          => $sms ? 1 : 0,
            'status'            => $last['status'],
            'externalMessageId' => null === $sent ? null : (string) self::faker()->numberBetween(100000000, 999999999),
            'statusHistory'     => array_map(static fn (array $change): array => [
                'status'     => $change['status'],
                'occurredAt' => $change['at']->setTimezone($utc)->format(\DATE_ATOM),
                'source'     => $change['source'],
                'reason'     => $change['reason'],
            ], $history),
            'createdAt'       => $history[0]['at']->setTimezone($utc),
            'sentAt'          => $sent?->setTimezone($utc),
            'statusChangedAt' => $last['at']->setTimezone($utc),
        ]);
    }

    protected function defaults(): array|callable
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $id  = Uuid::v7();

        return [
            'id'              => $id,
            'purpose'         => 'TEST',
            'channel'         => 'EMAIL',
            'criticality'     => 'CRITICAL',
            'subjectType'     => 'test',
            'subjectId'       => $id->toRfc4122(),
            'idempotencyKey'  => 'fixture:' . $id->toRfc4122(),
            'attachments'     => [],
            'status'          => 'QUEUED',
            'statusHistory'   => [],
            'createdAt'       => $now,
            'statusChangedAt' => $now,
        ];
    }
}
