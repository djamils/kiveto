<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Clinic\Security;

use App\Presentation\Clinic\Controller\Auth\LockController;
use App\Presentation\Clinic\Controller\Billing\IssueCreditNoteController;
use App\Presentation\Clinic\Controller\Consultation\Close\CloseConsultationController;
use App\Presentation\Clinic\Controller\Consultation\Record\RecordVitalsController;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\Security\ClinicRoutePermissions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

final class ClinicRoutePermissionsTest extends TestCase
{
    private ClinicRoutePermissions $routes;

    protected function setUp(): void
    {
        $this->routes = new ClinicRoutePermissions();
    }

    /**
     * Default deny: every controller of the clinic application has a rule,
     * so a new area cannot open its routes to every role by omission.
     */
    public function testEveryClinicControllerHasAPermissionRule(): void
    {
        $root  = \dirname(__DIR__, 5) . '/src/Presentation/Clinic/Controller';
        $count = 0;

        foreach ((new Finder())->files()->in($root)->name('*Controller.php') as $file) {
            $class = 'App\\Presentation\\Clinic\\Controller\\' . str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

            foreach (['GET', 'POST'] as $method) {
                $this->routes->permissionFor($class, $method);
            }
            ++$count;
        }

        self::assertGreaterThan(200, $count);
    }

    public function testAnAreaReadsWithOnePermissionAndWritesWithAnother(): void
    {
        self::assertSame(ClinicPermissionGuard::VIEW_MEDICAL_RECORD, $this->routes->permissionFor(CloseConsultationController::class, 'GET'));
        self::assertSame(ClinicPermissionGuard::VIEW_MEDICAL_RECORD, $this->routes->permissionFor(CloseConsultationController::class, 'HEAD'));
        self::assertSame(ClinicPermissionGuard::CREATE_CONSULTATION, $this->routes->permissionFor(CloseConsultationController::class . '::__invoke', 'POST'));
    }

    public function testTheAttributeOfAControllerWinsOverItsArea(): void
    {
        self::assertSame(ClinicPermissionGuard::RECORD_VITALS, $this->routes->permissionFor(RecordVitalsController::class, 'POST'));
        self::assertSame(ClinicPermissionGuard::MANAGE_BILLING, $this->routes->permissionFor(IssueCreditNoteController::class, 'POST'));
    }

    public function testSignInAndTheClinicChoiceNeedNoPermission(): void
    {
        self::assertNull($this->routes->permissionFor(LockController::class, 'POST'));
    }

    public function testAnAreaWithoutARuleIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The clinic area "Nowhere" of App\Presentation\Clinic\Controller\Nowhere\SomeController has no permission rule.');

        $this->routes->permissionFor('App\Presentation\Clinic\Controller\Nowhere\SomeController', 'GET');
    }

    public function testOnlyTheClinicControllersAreCovered(): void
    {
        self::assertTrue($this->routes->covers(CloseConsultationController::class . '::__invoke'));
        self::assertFalse($this->routes->covers('App\Presentation\Portal\Controller\LoginController'));
    }
}
