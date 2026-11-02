<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Adapter;

use App\Context\ClientPortal\Application\Command\DeletePortalAccount\DeletePortalAccount;
use App\Context\ClientPortal\Application\Port\DeletionCodeCheck;
use App\Context\ClientPortal\Infrastructure\Adapter\IdentityAccess\CommandBusPortalAccountErasure;
use App\Context\ClientPortal\Infrastructure\Adapter\Notification\CommandBusPortalRecipientMasker;
use App\Context\ClientPortal\Infrastructure\Messaging\MessengerAccountDeletionJobs;
use App\System\IdentityAccess\Application\Command\AnonymisePortalUser\AnonymisePortalUser;
use App\System\IdentityAccess\Application\Command\DisablePortalUser\DisablePortalUser;
use App\System\IdentityAccess\Application\Command\PurgePortalPhoneTraces\PurgedPortalIdentity;
use App\System\IdentityAccess\Application\Command\PurgePortalPhoneTraces\PurgePortalPhoneTraces;
use App\System\IdentityAccess\Application\Command\VerifyPortalActionOtp\ActionOtpResult;
use App\System\IdentityAccess\Application\Command\VerifyPortalActionOtp\VerifyPortalActionOtp;
use App\System\Notification\Application\Command\MaskPortalRecipient\MaskPortalRecipient;
use App\Tests\Unit\Context\ClientPortal\Support\AnsweringCommandBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class AccountDeletionAdaptersTest extends TestCase
{
    public function testTheAccountGoesThroughIdentityAccessCommands(): void
    {
        $bus = new AnsweringCommandBus(static fn (object $command): mixed => match (true) {
            $command instanceof VerifyPortalActionOtp  => ActionOtpResult::OTP_EXPIRED,
            $command instanceof PurgePortalPhoneTraces => new PurgedPortalIdentity('+33612345678', null),
            default                                    => null,
        });
        $accounts = new CommandBusPortalAccountErasure($bus);

        self::assertSame(DeletionCodeCheck::OTP_EXPIRED, $accounts->verifyDeletionCode('user-1', 'challenge-1', '123456'));
        $accounts->disable('user-1');
        $owner = $accounts->purgeTraces('user-1');
        $accounts->anonymise('user-1');

        self::assertSame(['+33612345678', null], [$owner->phone, $owner->email]);
        [$verify, $disable, $purge, $anonymise] = $bus->commands;
        self::assertInstanceOf(VerifyPortalActionOtp::class, $verify);
        self::assertSame(['user-1', 'challenge-1', '123456', 'account_deletion'], [$verify->userId, $verify->challengeId, $verify->code, $verify->purpose]);
        self::assertInstanceOf(DisablePortalUser::class, $disable);
        self::assertInstanceOf(PurgePortalPhoneTraces::class, $purge);
        self::assertInstanceOf(AnonymisePortalUser::class, $anonymise);
        self::assertSame(['user-1', 'user-1', 'user-1'], [$disable->userId, $purge->userId, $anonymise->userId]);
    }

    public function testTheMessagesAreMaskedByNotification(): void
    {
        $bus = new AnsweringCommandBus(static fn (): int => 2);

        new CommandBusPortalRecipientMasker($bus)->mask('user-1', '+33612345678', 's@example.com');

        [$mask] = $bus->commands;
        self::assertInstanceOf(MaskPortalRecipient::class, $mask);
        self::assertSame(['user-1', '+33612345678', 's@example.com'], [$mask->userId, $mask->phone, $mask->email]);
    }

    public function testTheErasureIsQueuedOnTheCommandBus(): void
    {
        $commands = new class implements MessageBusInterface {
            /** @var list<Envelope> */
            public array $dispatched = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return $this->dispatched[] = Envelope::wrap($message, $stamps);
            }
        };

        new MessengerAccountDeletionJobs($commands)->queueDeletion('user-1');

        $job = $commands->dispatched[0]->getMessage();
        self::assertInstanceOf(DeletePortalAccount::class, $job);
        self::assertSame(['user-1', 1], [$job->userId, \count($commands->dispatched)]);
    }
}
