<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Notification;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\RecordDispatchOutcome\RecordDispatchOutcome;
use App\System\Notification\Application\Command\RecordVeterinarianCount\RecordVeterinarianCount;
use App\System\Notification\Application\Command\UpsertClinicMessagingProfile\UpsertClinicMessagingProfile;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use App\System\Notification\Domain\Quota\SmsThreshold;
use App\System\Notification\Domain\Quota\SmsThresholdRepositoryInterface;
use App\Tests\Unit\System\Notification\Support\Messages;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SmsThresholdPersistenceTest extends KernelTestCase
{
    public function testACrossingIsRecordedOnce(): void
    {
        $thresholds = self::getContainer()->get('test.' . SmsThresholdRepositoryInterface::class);
        \assert($thresholds instanceof SmsThresholdRepositoryInterface);
        $at = new \DateTimeImmutable('2026-10-21T09:30:00+00:00');

        self::assertTrue($thresholds->markCrossed(Messages::CLINIC, '2026-10', SmsThreshold::EIGHTY, $at));
        self::assertFalse($thresholds->markCrossed(Messages::CLINIC, '2026-10', SmsThreshold::EIGHTY, $at));
        self::assertTrue($thresholds->markCrossed(Messages::CLINIC, '2026-11', SmsThreshold::EIGHTY, $at));
        self::assertTrue($thresholds->markCrossed(Messages::CLINIC, '2026-10', SmsThreshold::CAP, $at));
    }

    /**
     * AC 34 (Notification side): the SMS that reaches 80 % publishes the
     * threshold event to the outbox, in the transaction that marks it sent.
     */
    public function testTheSmsThatCrossesAThresholdPublishesIt(): void
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);
        $bus->dispatch(new UpsertClinicMessagingProfile(Messages::CLINIC, 'Clinique Montmartre', 'clinique-montmartre', null, 'Montmartre', 500, '18 rue Lepic', 'Paris 18e', '01 42 64 18 00', null, null));
        $bus->dispatch(new RecordVeterinarianCount(Messages::CLINIC, new \DateTimeImmutable()->format('Y-m-01'), 1));
        $messages = self::getContainer()->get('test.' . OutboundMessageRepositoryInterface::class);
        \assert($messages instanceof OutboundMessageRepositoryInterface);
        $messages->save(Messages::sms(segments: 330, at: new \DateTimeImmutable()));

        $bus->dispatch(new RecordDispatchOutcome('01960000-0000-7000-8000-000000000501', 'b-1', null, new \DateTimeImmutable()->format(\DATE_ATOM)));

        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);
        self::assertSame(1, $connection->fetchOne("SELECT COUNT(*) FROM shared__messenger_messages WHERE body LIKE '%SmsUsageThresholdCrossedIntegrationEvent%'"));
        self::assertSame(['80'], $connection->fetchFirstColumn('SELECT threshold FROM notification__sms_thresholds_crossed'));
    }
}
