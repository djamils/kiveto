<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic;

use App\Context\Clinic\Application\Command\Staff\DisableClinicMembership\DisableClinicMembership;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * A member whose membership is disabled while signed in loses the clinic at
 * the next request (MT-03), not when the session ends.
 */
final class DisabledMemberLosesAccessTest extends WebTestCase
{
    use Factories;

    private const string CLINIC     = '01960000-0000-7000-8000-0000000d15a1';
    private const string OTHER      = '01960000-0000-7000-8000-0000000d15a2';
    private const string MEMBERSHIP = '01960000-0000-7000-8000-0000000d15b1';
    private const string HOST       = 'http://clinic.kiveto.local';

    private KernelBrowser $browser;

    private string $email = '';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();
    }

    public function testADisabledMemberIsSentBackToTheClinicChoiceAtTheNextRequest(): void
    {
        $this->member(alsoOf: null);
        $this->signIn();

        $this->browser->request('GET', self::HOST . '/dashboard');
        self::assertResponseIsSuccessful();

        $this->disable();

        $this->browser->request('GET', self::HOST . '/dashboard');
        self::assertResponseRedirects('/select-clinic');

        // The clinic is forgotten: no page of it opens again.
        $this->browser->request('GET', self::HOST . '/dashboard');
        self::assertResponseRedirects('/select-clinic');
    }

    public function testAMemberOfAnotherClinicKeepsThatOne(): void
    {
        $this->member(alsoOf: self::OTHER);
        $this->signIn();
        $token = $this->browser->request('GET', self::HOST . '/select-clinic')->filter('input[name="_token"]')->attr('value');
        $this->browser->request('POST', self::HOST . '/select-clinic', ['clinic_id' => self::CLINIC, '_token' => (string) $token]);
        $this->browser->request('GET', self::HOST . '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Clinique des Saules', (string) $this->browser->getResponse()->getContent());

        $this->disable();

        $this->browser->request('GET', self::HOST . '/dashboard');
        self::assertResponseRedirects('/select-clinic');

        // The only clinic left is chosen for them.
        $this->browser->followRedirects();
        $this->browser->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Clinique des Saules', (string) $this->browser->getResponse()->getContent());
        self::assertStringContainsString('Clinique des Tilleuls', (string) $this->browser->getResponse()->getContent());
    }

    private function member(?string $alsoOf): void
    {
        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique des Saules')->create(['timeZone' => 'Europe/Paris']);

        $this->email = 'desactive.' . bin2hex(random_bytes(4)) . '@example.com';
        $vet         = ClinicUserFactory::new()->withEmail($this->email)->withPlainPassword('mot-de-passe')->create();
        ClinicMembershipEntityFactory::new()->withId(self::MEMBERSHIP)->withClinicId(self::CLINIC)->withUserId($vet->getId()->toRfc4122())->asVeterinary()->asEmployee()->create();

        if (null !== $alsoOf) {
            ClinicEntityFactory::new()->withId($alsoOf)->withName('Clinique des Tilleuls')->create(['timeZone' => 'Europe/Paris']);
            ClinicMembershipEntityFactory::new()->withClinicId($alsoOf)->withUserId($vet->getId()->toRfc4122())->asVeterinary()->asEmployee()->create();
        }
    }

    private function signIn(): void
    {
        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: json_encode(['email' => $this->email, 'password' => 'mot-de-passe'], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
    }

    private function disable(): void
    {
        $commandBus = self::getContainer()->get(CommandBusInterface::class);
        \assert($commandBus instanceof CommandBusInterface);
        $commandBus->dispatch(new DisableClinicMembership(self::MEMBERSHIP));
    }
}
