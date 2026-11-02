<?php

declare(strict_types=1);

namespace App\Fixtures\Context\ClientPortal\Story;

use App\Context\Client\Application\Command\SetClientConsent\SetClientConsent;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\ClientPortal\Application\Command\BuildDataExport\BuildDataExport;
use App\Context\ClientPortal\Application\Command\DeletePortalAccount\DeletePortalAccount;
use App\Context\ClientPortal\Application\Command\RequestAccountDeletion\RequestAccountDeletion;
use App\Context\ClientPortal\Application\Command\RequestDataExport\RequestDataExport;
use App\Context\ClientPortal\Application\Port\DeletionCodeCheck;
use App\Context\ClientPortal\Domain\Export\DataExport;
use App\Context\ClientPortal\Domain\Export\DataExportId;
use App\Context\ClientPortal\Domain\Export\DataExportRepositoryInterface;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Client\Factory\ContactMethodEntityFactory;
use App\Fixtures\Context\Client\Story\ConsentsStory;
use App\Fixtures\Context\ClientPortal\Factory\ClientLinkEntityFactory;
use App\Fixtures\Context\ClientPortal\Factory\OwnerProfileEntityFactory;
use App\Fixtures\Context\ClientPortal\Factory\PortalInvitationEntityFactory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Command\ConfirmPortalEmailChange\ConfirmPortalEmailChange;
use App\System\IdentityAccess\Application\Command\ConfirmPortalEmailChange\EmailChangeResult;
use App\System\IdentityAccess\Application\Command\RequestPortalActionOtp\RequestPortalActionOtp;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\OtpChallengeIssued;
use App\System\IdentityAccess\Application\Command\StartPortalEmailChange\EmailChangeStarted;
use App\System\IdentityAccess\Application\Command\StartPortalEmailChange\StartPortalEmailChange;
use App\System\IdentityAccess\Application\Command\VerifyPortalActionOtp\ActionOtpResult;
use App\System\Notification\Application\Command\DispatchOutboundMessage\DispatchOutboundMessage;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * The account states of slice 5 (e-mail, data exports, deletion), written
 * through the real commands: the one-time codes are read back from the
 * message journal, as the owner would read them on their phone.
 *
 * | Account                             | State                                                                           |
 * |-------------------------------------|---------------------------------------------------------------------------------|
 * | Thierry Marchand (+33612345680)     | **verified e-mail** thierry.marchand@orange.fr; exports: one **ready** (a real  |
 * |                                     | ZIP built by BuildDataExport, downloadable 48 h) and one **expired** (6 days)   |
 * | Sophie Rey (+33612345681)           | verified e-mail sophie.rey@free.fr and a **pending change** to                  |
 * |                                     | sophie.rey@laposte.net (open `email_verification` code, 10 min after the load); |
 * |                                     | one export **pending** (requested 2 minutes before the load, never built)       |
 * | Nicolas Lefèvre (was +33612345682)  | **anonymised**: deletion confirmed with its code, then DeletePortalAccount —    |
 * |                                     | phone and e-mail erased, profile « Compte supprimé », link revoked; his Paris   |
 * |                                     | client file, its consents and their history stay with the clinic                |
 * | Sophie Garnier (+33612345679)       | unchanged: e-mail copied from her client file, **not verified**                 |
 *
 * Lefèvre's code SMS is handed over before he confirms (as the worker
 * would), so the erasure masks it. The confirmed deletion also queued its erasure on `async` (it is run here at
 * once): a worker replaying it later finds nothing left to do. The pending
 * export has no build queued, so it stays pending.
 */
final class PortalAccountStatesStory extends Story
{
    public const string LEFEVRE_ID    = '019a3d00-0000-7000-8000-0000000000e3';
    public const string LEFEVRE_PHONE = '+33612345682';

    private const string PARIS = ClinicDataStory::INDEPENDENT_CLINIC_ID;

    private \DateTimeImmutable $now;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly ExternalApiCommandBusInterface $externalBus,
        private readonly DataExportRepositoryInterface $exports,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        #[Autowire('%env(PORTAL_TERMS_VERSION)%')]
        private readonly string $termsVersion,
    ) {
    }

    public function build(): void
    {
        // The exports carry the consents and their history.
        ConsentsStory::load();

        $this->now = $this->clock->now();

        $marchand = $this->portalUserId(PortalDocumentsStory::MARCHAND_PHONE);
        $this->verifyEmail($marchand, 'thierry.marchand@orange.fr');
        $this->expiredExport($marchand);
        $this->readyExport($marchand);

        $rey = $this->portalUserId(PortalDocumentsStory::REY_PHONE);
        $this->verifyEmail($rey, 'sophie.rey@free.fr');
        $this->startEmailChange($rey, 'sophie.rey@laposte.net');
        $this->exports->add(DataExport::request(DataExportId::fromString(Uuid::v7()->toRfc4122()), $rey, $this->now->modify('-2 minutes')));

        $this->deletedAccount();
    }

    /** The SMS code of the account, then the code sent to the address: the address is the account's, verified. */
    private function verifyEmail(string $userId, string $email): void
    {
        $verification = $this->startEmailChange($userId, $email);
        $result       = $this->commandBus->dispatch(new ConfirmPortalEmailChange($userId, $verification, $this->codeOf($verification)));
        \assert(EmailChangeResult::CONFIRMED === $result);
    }

    /** @return string the open `email_verification` challenge */
    private function startEmailChange(string $userId, string $email): string
    {
        $challenge = $this->actionCode($userId, 'email_change');
        $started   = $this->commandBus->dispatch(new StartPortalEmailChange($userId, $challenge, $this->codeOf($challenge), $email));
        \assert($started instanceof EmailChangeStarted && ActionOtpResult::VERIFIED === $started->result && null !== $started->verificationChallengeId);

        return $started->verificationChallengeId;
    }

    /** Requested and built as in production: the archive is stored and the export is ready for 48 hours. */
    private function readyExport(string $userId): void
    {
        $exportId = $this->commandBus->dispatch(new RequestDataExport($userId));
        \assert(\is_string($exportId));

        // The build queued on `async_external` is run here at once; the queued one will find it done.
        $this->externalBus->send(new BuildDataExport($exportId), new TransportNamesStamp(['sync']));
    }

    /** Ready six days ago: its archive was deleted 48 hours later. */
    private function expiredExport(string $userId): void
    {
        $requestedAt = $this->now->modify('-6 days');
        $export      = DataExport::request(DataExportId::fromString(Uuid::v7()->toRfc4122()), $userId, $requestedAt);
        $export->markReady($requestedAt->modify('+4 minutes'));
        $export->expire();
        $this->exports->add($export);
    }

    /**
     * Nicolas Lefèvre had an account linked to his Paris file and changed a
     * consent on the Portal; he deleted his account.
     */
    private function deletedAccount(): void
    {
        $linkedAt = $this->now->modify('-120 days');
        $since    = $this->now->modify('-5 years')->format('Y-m-d H:i:s');

        ClientEntityFactory::new()
            ->withId(self::LEFEVRE_ID)
            ->withClinicId(self::PARIS)
            ->withName('Nicolas', 'Lefèvre')
            ->withCivility('mister')
            ->since($since)
            ->withPostalAddress(streetLine1: '9 rue Marcadet', city: 'Paris', countryCode: 'FR', postalCode: '75018')
            ->withCommunicationPreferences('sms', ['appointment_reminders' => ['sms']], $since)
            ->active()
            ->create()
        ;
        ContactMethodEntityFactory::new()->forClient(self::LEFEVRE_ID)->phone(self::LEFEVRE_PHONE)->with(['label' => ContactLabel::MOBILE])->primary()->create();

        $userId = PortalUserFactory::new()->withPhone(self::LEFEVRE_PHONE)->create()->getId()->toRfc4122();

        PortalInvitationEntityFactory::new()
            ->forClient(self::PARIS, self::LEFEVRE_ID, self::LEFEVRE_PHONE, null)
            ->sentAt($linkedAt->modify('-1 day'))
            ->acceptedBy($userId, $linkedAt)
            ->create()
        ;
        ClientLinkEntityFactory::new()->between($userId, self::PARIS, self::LEFEVRE_ID)->linkedAt($linkedAt)->create();
        OwnerProfileEntityFactory::new()
            ->forUser($userId, 'Nicolas', 'Lefèvre')
            ->withAddress('9 rue Marcadet', '75018', 'Paris')
            ->acceptedTerms($this->termsVersion, $linkedAt)
            ->create()
        ;

        $this->commandBus->dispatch(new SetClientConsent(self::PARIS, self::LEFEVRE_ID, 'marketing', ['sms'], 'portal', 'portal_user', $userId, 'Nicolas Lefèvre'));

        $challenge = $this->actionCode($userId, 'account_deletion');
        $code      = $this->codeOf($challenge);
        $this->handOver(self::LEFEVRE_PHONE);
        $check = $this->commandBus->dispatch(new RequestAccountDeletion($userId, $challenge, $code));
        \assert(DeletionCodeCheck::VERIFIED === $check);

        // The erasure queued on `async`, run here at once.
        $this->commandBus->dispatch(new DeletePortalAccount($userId), new TransportNamesStamp(['sync']));
    }

    /**
     * The worker hands the queued messages to the phone over before the owner
     * can read the code (run here at once): the erasure masks only the
     * messages handed over, a queued one would keep its recipient and body.
     */
    private function handOver(string $phone): void
    {
        $ids = $this->connection->fetchFirstColumn(
            "SELECT BIN_TO_UUID(id) FROM notification__outbound_messages WHERE recipient_address = ? AND status = 'QUEUED'",
            [$phone],
        );

        foreach ($ids as $id) {
            \assert(\is_string($id));
            $this->externalBus->send(new DispatchOutboundMessage($id), new TransportNamesStamp(['sync']));
        }
    }

    /** @return string the challenge of the SMS code sent to the account's phone */
    private function actionCode(string $userId, string $purpose): string
    {
        $issued = $this->commandBus->dispatch(new RequestPortalActionOtp($userId, $purpose));
        \assert($issued instanceof OtpChallengeIssued);

        return $issued->challengeId;
    }

    /** The code of a challenge, as the journal of the first message sent for it reads. */
    private function codeOf(string $challengeId): string
    {
        $content = $this->connection->fetchOne('SELECT content FROM notification__outbound_messages WHERE idempotency_key = ?', ['otp:' . $challengeId . ':1']);
        if (!\is_string($content) || 1 !== preg_match('/(?<![#\d])(\d{6})(?!\d)/', $content, $match)) {
            throw new \LogicException(\sprintf('No code was sent for challenge %s.', $challengeId));
        }

        return $match[1];
    }

    private function portalUserId(string $phone): string
    {
        $id = $this->connection->fetchOne("SELECT BIN_TO_UUID(id) FROM identity_access__users WHERE type = 'PORTAL' AND phone = ?", [$phone]);
        \assert(\is_string($id), \sprintf('The Portal dataset creates the owner account %s.', $phone));

        return $id;
    }
}
