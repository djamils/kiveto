<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Application\Port\BookingDeclinedMessage;
use App\Context\ClientPortal\Application\Port\ClientDirectoryInterface;
use App\Context\ClientPortal\Application\Port\ClinicContact;
use App\Context\ClientPortal\Application\Port\ClinicLabel;
use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\DataExportReadyMessage;
use App\Context\ClientPortal\Application\Port\IdentityClaim;
use App\Context\ClientPortal\Application\Port\IdentityClaimRequest;
use App\Context\ClientPortal\Application\Port\IdentityClaimResult;
use App\Context\ClientPortal\Application\Port\InvitationLinkBuilderInterface;
use App\Context\ClientPortal\Application\Port\MobileFileMatch;
use App\Context\ClientPortal\Application\Port\MobilePhonePolicyInterface;
use App\Context\ClientPortal\Application\Port\NotificationRequesterInterface;
use App\Context\ClientPortal\Application\Port\PetNamesInterface;
use App\Context\ClientPortal\Application\Port\PortalClientContact;
use App\Context\ClientPortal\Application\Port\PortalClientSnapshot;
use App\Context\ClientPortal\Application\Port\PortalIdentityInterface;
use App\Context\ClientPortal\Application\Port\PortalInvitationMessage;
use App\Context\ClientPortal\Application\Port\PortalRegistration;
use App\Context\ClientPortal\Application\Port\PortalRegistrationResult;
use App\Context\ClientPortal\Application\Port\PortalRequestResolvedMessage;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Document\DocumentClinic;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Security\OpaqueTokenHasherInterface;
use App\Shared\Domain\ValueObject\PostalAddress;

/**
 * Hand-written doubles of every ClientPortal port: one client file, named
 * clinics, pets, a switchable mobile policy, recorded messages, predictable
 * tokens and ids, and an owner-account claim answered by a closure.
 */
final class ClientPortalFakes implements ClientDirectoryInterface, ClinicProfileInterface, PetNamesInterface, MobilePhonePolicyInterface, NotificationRequesterInterface, InvitationLinkBuilderInterface, PortalIdentityInterface, OpaqueTokenHasherInterface, UuidGeneratorInterface, EventBusInterface
{
    public const string USER = '01960000-0000-7000-8000-0000000c0001';

    /** @var array<string, PortalClientContact> by "clinic/client" */
    public array $contacts = [];

    /** @var array<string, PortalClientSnapshot> by "clinic/client" */
    public array $snapshots = [];

    /** @var array<string, list<MobileFileMatch>> by phone */
    public array $mobileMatches = [];

    /** @var array<string, ClinicLabel> by clinic id */
    public array $clinics = [];

    /** @var array<string, DocumentClinic> by clinic id */
    public array $headers = [];

    /** @var array<string, ClinicContact> by clinic id */
    public array $clinicContacts = [];

    /** @var array<string, list<string>> by "clinic/client" */
    public array $pets = [];

    public bool $mobile = true;

    /** @var list<PortalInvitationMessage> */
    public array $messages = [];

    /** @var list<DataExportReadyMessage> */
    public array $exportNotices = [];

    /** @var list<PortalRequestResolvedMessage> */
    public array $resolutionNotices = [];

    /** @var list<BookingDeclinedMessage> */
    public array $bookingDeclines = [];

    /** @var list<IdentityClaimRequest> */
    public array $claims = [];

    /** @var \Closure(IdentityClaimRequest): IdentityClaim */
    public \Closure $claimAnswer;

    /** @var list<string> phones whose grants were revoked */
    public array $revokedGrants = [];

    /** @var list<array{string, ?string}> registrations asked: token, e-mail */
    public array $registrations = [];

    /** @var \Closure(string): PortalRegistration may throw (an existing account) */
    public \Closure $registrationAnswer;

    /** @var list<DomainEventInterface> */
    public array $events = [];

    private int $secrets = 0;

    private int $ids = 0;

    public function __construct()
    {
        $this->claimAnswer        = static fn (IdentityClaimRequest $request): IdentityClaim => new IdentityClaim(IdentityClaimResult::VERIFIED, self::USER);
        $this->registrationAnswer = static fn (string $token): PortalRegistration => new PortalRegistration(PortalRegistrationResult::REGISTERED, self::USER);
    }

    public function withClient(string $clinicId, string $clientId, ?string $mobile = '+33612345678', ?string $email = 'sophie@example.com', bool $archived = false): self
    {
        $this->contacts[$clinicId . '/' . $clientId]  = new PortalClientContact($clientId, 'Sophie Martin', 'Sophie', $email, $mobile, $archived);
        $this->snapshots[$clinicId . '/' . $clientId] = new PortalClientSnapshot('Sophie', 'Martin', PostalAddress::create('12 rue des Lilas', 'Lyon', 'fr', 'Bât. B', '69003'));

        return $this;
    }

    public function contactOf(string $clinicId, string $clientId): ?PortalClientContact
    {
        return $this->contacts[$clinicId . '/' . $clientId] ?? null;
    }

    public function snapshotOf(string $clinicId, string $clientId): ?PortalClientSnapshot
    {
        return $this->snapshots[$clinicId . '/' . $clientId] ?? null;
    }

    public function mobileMatches(string $phone): array
    {
        return $this->mobileMatches[$phone] ?? [];
    }

    public function clinicOf(string $clinicId): ClinicLabel
    {
        return $this->clinics[$clinicId] ?? new ClinicLabel('', null);
    }

    public function documentHeaderOf(string $clinicId): ?DocumentClinic
    {
        return $this->headers[$clinicId] ?? null;
    }

    public function contactDetailsOf(string $clinicId): ClinicContact
    {
        return $this->clinicContacts[$clinicId] ?? new ClinicContact(null, null);
    }

    public function petNamesOf(string $clinicId, string $clientId): array
    {
        return $this->pets[$clinicId . '/' . $clientId] ?? [];
    }

    public function isPortalMobile(string $phone): bool
    {
        return $this->mobile;
    }

    public function requestPortalInvitation(PortalInvitationMessage $message): void
    {
        $this->messages[] = $message;
    }

    public function requestDataExportReady(DataExportReadyMessage $message): void
    {
        $this->exportNotices[] = $message;
    }

    public function requestPortalRequestResolved(PortalRequestResolvedMessage $message): string
    {
        $this->resolutionNotices[] = $message;

        return \sprintf('01960000-0000-7000-8000-0000000e%04d', \count($this->resolutionNotices));
    }

    public function requestBookingDeclined(BookingDeclinedMessage $message): void
    {
        $this->bookingDeclines[] = $message;
    }

    public function invitationLink(string $token): string
    {
        return 'https://portal.test/invitation#t=' . $token;
    }

    public function claimIdentity(IdentityClaimRequest $request): IdentityClaim
    {
        $this->claims[] = $request;

        return ($this->claimAnswer)($request);
    }

    public function answerClaims(IdentityClaimResult $result, ?string $userId = null): void
    {
        $this->claimAnswer = static fn (IdentityClaimRequest $request): IdentityClaim => new IdentityClaim($result, $userId);
    }

    public function revokeOnboardingGrants(string $phone): void
    {
        $this->revokedGrants[] = $phone;
    }

    public function registerPortalUser(#[\SensitiveParameter] string $registrationToken, ?string $email): PortalRegistration
    {
        $this->registrations[] = [$registrationToken, $email];

        return ($this->registrationAnswer)($registrationToken);
    }

    /** `secret-1`, `secret-2`… */
    public function newSecret(): string
    {
        return 'secret-' . ++$this->secrets;
    }

    public function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }

    public function generate(): string
    {
        return \sprintf('01960000-0000-7000-8000-%012d', ++$this->ids);
    }

    public function publish(array $stamps, object ...$events): void
    {
        foreach ($events as $event) {
            \assert($event instanceof DomainEventInterface);
            $this->events[] = $event;
        }
    }

    /**
     * @return list<string>
     */
    public function eventNames(): array
    {
        return array_map(static fn (DomainEventInterface $e): string => $e->name(), $this->events);
    }
}
