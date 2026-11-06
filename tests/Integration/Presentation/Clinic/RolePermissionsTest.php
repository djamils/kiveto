<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic;

use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * Every clinic route requires the permission of its area or of its
 * controller (ClinicRoutePermissions), checked before the controller runs:
 * the answers below are the permission's, whatever the ids in the path. A
 * refusal is a JSON 403 for the scripts, the dashboard and a flash for a
 * form, the 403 page for a page.
 */
final class RolePermissionsTest extends WebTestCase
{
    use Factories;

    private const string CLINIC  = '01960000-0000-7000-8000-0000000e01a1';
    private const string HOST    = 'http://clinic.kiveto.local';
    private const string UNKNOWN = '01960000-0000-7000-8000-0000000e0fff';

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();
        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique des Rôles')->create(['timeZone' => 'Europe/Paris']);
    }

    public function testTheReceptionReadsTheRecordCollectsPaymentsButNeitherWritesCareNorManagesStock(): void
    {
        $this->signInAs('receptionist');

        $this->browser->request('GET', self::HOST . '/consultations');
        self::assertResponseIsSuccessful();
        $this->browser->request('GET', self::HOST . '/invoices');
        self::assertResponseIsSuccessful();

        self::assertSame('FORBIDDEN', $this->script('/clinic/consultations/' . self::UNKNOWN . '/vitals'));
        self::assertResponseStatusCodeSame(403);
        self::assertSame('FORBIDDEN', $this->script('/clinic/consultations/' . self::UNKNOWN . '/close'));

        // A form posted anyway comes back to the dashboard.
        $this->browser->request('POST', self::HOST . '/clinic/consultations/' . self::UNKNOWN . '/close');
        self::assertResponseRedirects('/dashboard');

        $this->browser->request('GET', self::HOST . '/stock');
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheAssistantRecordsVitalsAndManagesStockButDoesNotCloseAConsultation(): void
    {
        $this->signInAs('assistant');

        // Let through to the cockpit, which then asks for its CSRF token.
        self::assertSame('CSRF_INVALID', $this->script('/clinic/consultations/' . self::UNKNOWN . '/vitals'));
        $this->browser->request('GET', self::HOST . '/stock');
        self::assertResponseIsSuccessful();

        self::assertSame('FORBIDDEN', $this->script('/clinic/consultations/' . self::UNKNOWN . '/close'));
    }

    public function testCreditNotesCatalogueChangesAndReportingAreTheAdministrations(): void
    {
        $this->signInAs('vet');

        $this->browser->request('GET', self::HOST . '/catalog');
        self::assertResponseIsSuccessful();

        foreach (['/invoices/' . self::UNKNOWN . '/credit-note', '/catalog/acts'] as $path) {
            self::assertSame('FORBIDDEN', $this->script($path), $path);
            self::assertResponseStatusCodeSame(403);
        }
        $this->browser->request('GET', self::HOST . '/reporting');
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheAdministratorHatOpensThem(): void
    {
        $this->signInAs('administrator');

        $this->browser->request('GET', self::HOST . '/reporting');
        self::assertResponseIsSuccessful();
        self::assertNotSame('FORBIDDEN', $this->script('/invoices/' . self::UNKNOWN . '/credit-note'));
    }

    /**
     * Posts as the scripts of the application do.
     *
     * @return string|null the errorCode of the answer
     */
    private function script(string $path): ?string
    {
        $this->browser->request('POST', self::HOST . $path, server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $answer = json_decode((string) $this->browser->getResponse()->getContent(), true);

        return \is_array($answer) && \is_string($answer['errorCode'] ?? null) ? $answer['errorCode'] : null;
    }

    /**
     * @param 'receptionist'|'assistant'|'vet'|'administrator' $who
     */
    private function signInAs(string $who): void
    {
        $email = $who . '.' . bin2hex(random_bytes(4)) . '@example.com';
        $user  = ClinicUserFactory::new()->withEmail($email)->withPlainPassword('mot-de-passe')->create();

        $membership = ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($user->getId()->toRfc4122())->asEmployee();
        $membership = match ($who) {
            'receptionist'  => $membership->asReceptionist(),
            'assistant'     => $membership->asVeterinaryAssistant(),
            'vet'           => $membership->asVeterinary(),
            'administrator' => $membership->asManager()->asClinicAdministrator(),
        };
        $membership->create();

        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: json_encode(['email' => $email, 'password' => 'mot-de-passe'], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
    }
}
