<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\IdentityAccess\Application\Command\ConsoleTeam;

use App\Fixtures\System\IdentityAccess\Factory\BackofficeUserFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Application\Command\ActivateInvitedUser\ActivateInvitedUser;
use App\System\IdentityAccess\Application\Command\ChangeConsoleRole\ChangeConsoleRole;
use App\System\IdentityAccess\Application\Command\InviteConsoleMember\ConsoleInvitation;
use App\System\IdentityAccess\Application\Command\InviteConsoleMember\InviteConsoleMember;
use App\System\IdentityAccess\Application\Command\SwitchConsoleMember\SwitchConsoleMember;
use App\System\IdentityAccess\Application\Port\BackofficeProfileStoreInterface;
use App\System\IdentityAccess\Application\Query\CheckAccountToken\AccountTokenCheck;
use App\System\IdentityAccess\Application\Query\CheckAccountToken\CheckAccountToken;
use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\BackofficeProfileView;
use App\System\IdentityAccess\Application\Query\ListConsoleTeam\ListConsoleTeam;
use App\System\IdentityAccess\Domain\ValueObject\ConsoleRole;
use App\System\IdentityAccess\Infrastructure\Console\InviteConsoleMemberCommand;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Zenstruck\Foundry\Test\Factories;

/**
 * The Kiveto team in the console: invited with a link, the password chosen
 * from it, a role, switched off and on; the console keeps an active
 * administrator; a session carries the role.
 */
final class ConsoleTeamTest extends KernelTestCase
{
    use Factories;

    public function testAMemberIsInvitedThenChoosesTheirPassword(): void
    {
        $invitation = $this->invite(' Lea@Kiveto.app ', 'Léa Martin', 'support');

        self::assertSame('lea@kiveto.app', $invitation->email);
        self::assertMatchesRegularExpression('#^https?://backoffice\.[^/]+/invitation/[^/]+$#', $invitation->url, 'the console\'s host');
        self::assertGreaterThan(new \DateTimeImmutable('+6 days'), $invitation->expiresAt);

        $member = $this->profiles()->find($invitation->userId);
        self::assertSame(['Léa Martin', 'support', 'PENDING'], [$member?->displayName, $member?->role, $member?->status]);

        $secret = self::secret($invitation);
        self::assertSame(AccountTokenCheck::VALID, $this->check($secret)->state);
        self::assertSame(AccountTokenCheck::INVALID, $this->check($secret, CheckAccountToken::INVITATION)->state, 'not a clinic invitation');

        $this->bus()->dispatch(new ActivateInvitedUser($secret, 'un mot de passe solide'));

        self::assertSame('ACTIVE', $this->profiles()->find($invitation->userId)?->status);
        self::assertSame(AccountTokenCheck::INVALID, $this->check($secret)->state, 'used');

        try {
            $this->invite('lea@kiveto.app', 'Léa', 'admin');
            self::fail('an account already in use');
        } catch (\DomainException $exception) {
            self::assertSame('Cette adresse a déjà un compte console.', $exception->getMessage());
        }
    }

    public function testInvitingAgainGivesANewLinkAndKillsTheOld(): void
    {
        $first  = $this->invite('noe@kiveto.app', '', 'support');
        $second = $this->invite('noe@kiveto.app', 'Noé', 'admin');

        self::assertSame($first->userId, $second->userId);
        self::assertSame(AccountTokenCheck::INVALID, $this->check(self::secret($first))->state);
        self::assertSame(AccountTokenCheck::VALID, $this->check(self::secret($second))->state);
        self::assertSame(['Noé', 'admin'], [$this->profiles()->find($second->userId)?->displayName, $this->profiles()->find($second->userId)?->role]);

        $nameless = $this->invite('sans.nom@kiveto.app', '  ', 'support');
        self::assertSame('Sans Nom', $this->profiles()->find($nameless->userId)?->displayName, 'the name from the address');
    }

    public function testAWrongAddressOrRoleIsRefused(): void
    {
        foreach ([['pas-une-adresse', 'support', 'Adresse e-mail invalide.'], ['ok@kiveto.app', 'chef', 'Choisis un rôle']] as [$email, $role, $message]) {
            try {
                $this->invite($email, 'X', $role);
                self::fail('refused: ' . $email);
            } catch (\DomainException $exception) {
                self::assertStringContainsString($message, $exception->getMessage());
            }
        }
    }

    public function testTheConsoleKeepsAnActiveAdministrator(): void
    {
        $admin   = $this->member('admin@kiveto.app');
        $support = $this->member('support@kiveto.app', ConsoleRole::SUPPORT);

        self::assertFalse($this->bus()->dispatch(new ChangeConsoleRole($admin, 'admin')), 'nothing changed');
        $this->refused(new ChangeConsoleRole($admin, 'support'), 'au moins un administrateur actif');
        $this->refused(new SwitchConsoleMember($admin, false, $support), 'au moins un administrateur actif');
        $this->refused(new SwitchConsoleMember($admin, false, $admin), 'Tu ne peux pas te désactiver toi-même.');
        $this->refused(new ChangeConsoleRole($admin, 'chef'), 'Choisis un rôle');
        $this->refused(new ChangeConsoleRole('01960000-0000-7000-8000-00000000dead', 'admin'), 'introuvable');
        $this->refused(new SwitchConsoleMember('01960000-0000-7000-8000-00000000dead', false, $admin), 'introuvable');

        self::assertTrue($this->bus()->dispatch(new ChangeConsoleRole($support, 'admin')));
        self::assertTrue($this->bus()->dispatch(new ChangeConsoleRole($admin, 'support')), 'another administrator now');
        self::assertSame('support', $this->profiles()->find($admin)?->role);
    }

    public function testAMemberIsSwitchedOffThenOn(): void
    {
        $admin   = $this->member('admin@kiveto.app');
        $support = $this->member('support@kiveto.app', ConsoleRole::SUPPORT);

        self::assertSame(0, $this->bus()->dispatch(new SwitchConsoleMember($support, false, $admin)), 'no session to close');
        self::assertSame('DISABLED', $this->profiles()->find($support)?->status);

        $team = $this->ask(new ListConsoleTeam());
        \assert(\is_array($team));
        self::assertSame(['admin@kiveto.app', 'support@kiveto.app'], array_values(array_map(static fn (BackofficeProfileView $m): string => $m->email, array_filter($team, static fn (mixed $m): bool => $m instanceof BackofficeProfileView))), 'the disabled ones last');

        $this->refused(new InviteConsoleMember('support@kiveto.app', 'S', 'support'), 'réactive-le');

        $this->bus()->dispatch(new SwitchConsoleMember($support, true, $admin));
        self::assertSame('ACTIVE', $this->profiles()->find($support)?->status);
    }

    public function testASessionCarriesTheRole(): void
    {
        $this->member('support@kiveto.app', ConsoleRole::SUPPORT);
        $this->member('legacy@kiveto.app', null);

        $provider = static::getContainer()->get('App\System\IdentityAccess\Infrastructure\Security\Symfony\ContextUserProvider.backoffice');
        \assert($provider instanceof UserProviderInterface);

        $support = $provider->loadUserByIdentifier('support@kiveto.app');
        $legacy  = $provider->loadUserByIdentifier('legacy@kiveto.app');
        \assert($support instanceof SecurityUser && $legacy instanceof SecurityUser);

        self::assertSame(['ROLE_USER', 'ROLE_CONSOLE_SUPPORT'], $support->getRoles());
        self::assertSame(['ROLE_USER', 'ROLE_CONSOLE_ADMIN'], $legacy->getRoles(), 'an account without a profile is an administrator');

        ClinicUserFactory::new()->withEmail('vet@clinic.test')->withPlainPassword('mot-de-passe')->create();
        $clinics = static::getContainer()->get('App\System\IdentityAccess\Infrastructure\Security\Symfony\ContextUserProvider.clinic');
        \assert($clinics instanceof UserProviderInterface);
        self::assertSame(['ROLE_USER'], $clinics->loadUserByIdentifier('vet@clinic.test')->getRoles(), 'no console role for a clinic account');
    }

    public function testTheFirstAdministratorIsInvitedOnTheCommandLine(): void
    {
        $command = static::getContainer()->get(InviteConsoleMemberCommand::class);
        \assert($command instanceof Command);
        $tester = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'premier@kiveto.app', 'name' => 'Premier Admin']));
        self::assertStringContainsString('premier@kiveto.app is invited.', $tester->getDisplay());
        self::assertStringContainsString('/invitation/', $tester->getDisplay());

        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'premier@kiveto.app', 'name' => 'Premier', '--role' => 'chef']));
        self::assertStringContainsString('Choisis un rôle', $tester->getDisplay());
    }

    private function invite(string $email, string $name, string $role): ConsoleInvitation
    {
        $invitation = $this->bus()->dispatch(new InviteConsoleMember($email, $name, $role));
        \assert($invitation instanceof ConsoleInvitation);

        return $invitation;
    }

    /** An active console account, with a profile of this role (none for null). */
    private function member(string $email, ?ConsoleRole $role = ConsoleRole::ADMIN): string
    {
        $id = BackofficeUserFactory::new()->withEmail($email)->withPlainPassword('mot-de-passe')->create()->getId()->toRfc4122();

        if (null !== $role) {
            $this->profiles()->saveRole($id, $role, new \DateTimeImmutable());
        }

        return $id;
    }

    private function refused(object $command, string $message): void
    {
        try {
            $this->bus()->dispatch($command);
            self::fail('refused: ' . $message);
        } catch (\DomainException $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
        }
    }

    private function check(string $secret, string $purpose = CheckAccountToken::CONSOLE_INVITATION): AccountTokenCheck
    {
        $check = $this->ask(new CheckAccountToken($secret, $purpose));
        \assert($check instanceof AccountTokenCheck);

        return $check;
    }

    private static function secret(ConsoleInvitation $invitation): string
    {
        return rawurldecode(substr($invitation->url, (int) strrpos($invitation->url, '/') + 1));
    }

    private function ask(object $query): mixed
    {
        $bus = static::getContainer()->get(QueryBusInterface::class);
        \assert($bus instanceof QueryBusInterface);

        return $bus->ask($query);
    }

    private function bus(): CommandBusInterface
    {
        $bus = static::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus;
    }

    private function profiles(): BackofficeProfileStoreInterface
    {
        $profiles = static::getContainer()->get(BackofficeProfileStoreInterface::class);
        \assert($profiles instanceof BackofficeProfileStoreInterface);

        return $profiles;
    }
}
