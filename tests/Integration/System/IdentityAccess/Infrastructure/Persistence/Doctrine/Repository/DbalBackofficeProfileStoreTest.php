<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\Fixtures\System\IdentityAccess\Factory\BackofficeUserFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\System\IdentityAccess\Application\Port\BackofficeProfileStoreInterface;
use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\BackofficeProfileView;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DbalBackofficeProfileStoreTest extends KernelTestCase
{
    use Factories;

    public function testAnAccountThatNeverSavedItsSettingsReadsTheDefaults(): void
    {
        $id = $this->backoffice('djamil.soidiki@kiveto.fr', new \DateTimeImmutable('2026-01-15 09:30:00', new \DateTimeZone('UTC')));

        $view = $this->profiles()->find($id);

        self::assertNotNull($view);
        self::assertSame([$id, 'djamil.soidiki@kiveto.fr', 'Djamil Soidiki', 'Europe/Paris', true, true, '2026-01-15 09:30:00', 'UTC'], [
            $view->userId, $view->email, $view->displayName, $view->timeZone, $view->notifyOnRegistryFailure, $view->notifyOnInvitationExpiry,
            $view->memberSince->format('Y-m-d H:i:s'), $view->memberSince->getTimezone()->getName(),
        ]);
    }

    public function testSavedSettingsAreReadBackAndReplacedBySavingAgain(): void
    {
        $profiles = $this->profiles();
        $id       = $this->backoffice('admin@kiveto.fr', new \DateTimeImmutable('2026-01-15 09:30:00', new \DateTimeZone('UTC')));

        $profiles->save($id, 'Djamil', 'America/Martinique', false, true, new \DateTimeImmutable('2026-11-06 10:00:00', new \DateTimeZone('Europe/Paris')));
        self::assertSame(['Djamil', 'America/Martinique', false, true], $this->settings($profiles->find($id)));

        $profiles->save($id, 'Djamil S.', 'Europe/Paris', true, false, new \DateTimeImmutable('2026-11-07 10:00:00', new \DateTimeZone('Europe/Paris')));
        self::assertSame(['Djamil S.', 'Europe/Paris', true, false], $this->settings($profiles->find($id)));
    }

    public function testOnlyABackOfficeAccountHasAProfile(): void
    {
        $clinic = ClinicUserFactory::new()->withEmail('vet@clinique.fr')->create()->getId()->toRfc4122();

        self::assertNull($this->profiles()->find($clinic));
        self::assertNull($this->profiles()->find('01960000-0000-7000-8000-000000006eff'));
    }

    public function testTheTeamIsItsActiveBackOfficeAccountsByName(): void
    {
        $profiles = $this->profiles();
        $at       = new \DateTimeImmutable('2026-01-15 09:30:00', new \DateTimeZone('UTC'));
        $zoe      = $this->backoffice('zoe@kiveto.fr', $at);
        $bruno    = $this->backoffice('bruno@kiveto.fr', $at);
        $alice    = $this->backoffice('zz-last@kiveto.fr', $at);
        $profiles->save($alice, 'alice', 'Europe/Paris', true, true, $at);
        $gone   = BackofficeUserFactory::new()->withEmail('gone@kiveto.fr')->disabled()->create()->getId()->toRfc4122();
        $clinic = ClinicUserFactory::new()->withEmail('anne@clinique.fr')->create()->getId()->toRfc4122();

        $team = array_values(array_filter(
            $profiles->all(),
            static fn (BackofficeProfileView $view): bool => \in_array($view->userId, [$zoe, $bruno, $alice, $gone, $clinic], true),
        ));

        self::assertSame([[$alice, 'alice'], [$bruno, 'Bruno'], [$zoe, 'Zoe']], array_map(static fn (BackofficeProfileView $view): array => [$view->userId, $view->displayName], $team));
    }

    /**
     * @return array{string, string, bool, bool}|null
     */
    private function settings(?BackofficeProfileView $view): ?array
    {
        return null === $view ? null : [$view->displayName, $view->timeZone, $view->notifyOnRegistryFailure, $view->notifyOnInvitationExpiry];
    }

    private function backoffice(string $email, \DateTimeImmutable $createdAt): string
    {
        return BackofficeUserFactory::new()->withEmail($email)->with(['createdAt' => $createdAt])->create()->getId()->toRfc4122();
    }

    private function profiles(): BackofficeProfileStoreInterface
    {
        $store = self::getContainer()->get(BackofficeProfileStoreInterface::class);
        \assert($store instanceof BackofficeProfileStoreInterface);

        return $store;
    }
}
