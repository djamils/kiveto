<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Support;

use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Event\AbstractEvent;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\System\IdentityAccess\Application\Port\AccountLinkBuilderInterface;
use App\System\IdentityAccess\Application\Port\NotificationRequesterInterface;
use App\System\IdentityAccess\Application\Port\PasswordHasherInterface;
use App\System\IdentityAccess\Application\Port\PasswordResetRequest;
use App\System\IdentityAccess\Application\Port\PortalEmailChangedNoticeRequest;
use App\System\IdentityAccess\Application\Port\PortalEmailVerificationRequest;
use App\System\IdentityAccess\Application\Port\PortalOtpRequest;
use App\System\IdentityAccess\Application\Port\StaffInvitationRequest;
use App\System\IdentityAccess\Domain\ValueObject\UserType;

/**
 * Hand-written doubles of the account use cases: a readable hasher, plain
 * links, a recording notification requester and event bus, sequential ids.
 */
final class AccountFakes implements PasswordHasherInterface, AccountLinkBuilderInterface, NotificationRequesterInterface, UuidGeneratorInterface, EventBusInterface
{
    /** @var list<StaffInvitationRequest> */
    public array $invitations = [];

    /** @var list<PasswordResetRequest> */
    public array $resets = [];

    /** @var list<PortalOtpRequest> */
    public array $portalOtps = [];

    /** @var list<array{PortalOtpRequest, string}> codes resent by e-mail, with the address */
    public array $emailedOtps = [];

    /** @var list<PortalEmailVerificationRequest> */
    public array $emailVerifications = [];

    /** @var list<PortalEmailChangedNoticeRequest> */
    public array $emailChangedNotices = [];

    /** @var list<object> */
    public array $events = [];

    private int $sequence = 0;

    public function hash(UserType $type, string $plainPassword): string
    {
        return 'hashed:' . $plainPassword;
    }

    public function invitationUrl(string $secret): string
    {
        return 'https://clinic/invitation/' . $secret;
    }

    public function passwordResetUrl(string $secret): string
    {
        return 'https://clinic/reinitialiser/' . $secret;
    }

    public function backofficePasswordResetUrl(string $secret): string
    {
        return 'https://backoffice/reinitialiser/' . $secret;
    }

    public function supportHandoffUrl(string $secret): string
    {
        return 'https://clinic/support/' . $secret;
    }

    public function consoleInvitationUrl(string $secret): string
    {
        return 'https://backoffice/invitation/' . $secret;
    }

    public function requestStaffInvitation(StaffInvitationRequest $request): void
    {
        $this->invitations[] = $request;
    }

    public function requestPasswordReset(PasswordResetRequest $request): void
    {
        $this->resets[] = $request;
    }

    public function requestPortalOtp(PortalOtpRequest $request): void
    {
        $this->portalOtps[] = $request;
    }

    public function requestPortalOtpByEmail(PortalOtpRequest $request, string $email): void
    {
        $this->emailedOtps[] = [$request, $email];
    }

    public function requestPortalEmailVerification(PortalEmailVerificationRequest $request): void
    {
        $this->emailVerifications[] = $request;
    }

    public function requestPortalEmailChangedNotice(PortalEmailChangedNoticeRequest $request): void
    {
        $this->emailChangedNotices[] = $request;
    }

    public function generate(): string
    {
        return \sprintf('01960000-0000-7000-8000-%012d', ++$this->sequence);
    }

    public function publish(array $stamps, object ...$events): void
    {
        array_push($this->events, ...$events);
    }

    public function publisher(): DomainEventPublisher
    {
        return new DomainEventPublisher($this);
    }

    /** The secret carried by a link built here. */
    public static function secretOf(string $url): string
    {
        return substr($url, (int) strrpos($url, '/') + 1);
    }

    /**
     * @return list<string>
     */
    public function eventNames(): array
    {
        return array_map(static fn (object $event): string => $event instanceof AbstractEvent ? $event->name() : $event::class, $this->events);
    }
}
