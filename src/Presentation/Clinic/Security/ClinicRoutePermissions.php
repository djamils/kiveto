<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Security;

use App\Presentation\Clinic\Security\ClinicPermissionGuard as P;

/**
 * Which permission a route of the clinic application requires, so that none
 * is open to every role by omission (AUTHZ-01, AUTHZ-02):
 *
 * - the #[RequiresClinicPermission] of its controller, when it has one;
 * - else the rule of its area, the folder right under Controller\: one
 *   permission to read (GET, HEAD), one to write (any other method);
 * - Auth needs none (sign-in, clinic choice, lock, account links);
 * - an area missing here is refused, and ClinicRoutePermissionsTest fails
 *   until it is given its rule.
 *
 * Settings and Portal requests only ask for membership here: each of their
 * controllers requires its own permission (SettingsEndpoint, TeamEndpoint,
 * the Portal request controllers).
 */
final class ClinicRoutePermissions
{
    private const string NAMESPACE = 'App\\Presentation\\Clinic\\Controller\\';

    private const string PUBLIC_AREA = 'Auth';

    /** @var array<string, array{0: string, 1: string}> area => [read, write] */
    private const array AREAS = [
        'Admission'     => [P::MANAGE_APPOINTMENTS, P::MANAGE_APPOINTMENTS],
        'Animal'        => [P::MANAGE_ANIMALS, P::MANAGE_ANIMALS],
        'Api'           => [P::MANAGE_CLIENTS, P::MANAGE_CLIENTS],
        'Billing'       => [P::COLLECT_PAYMENTS, P::COLLECT_PAYMENTS],
        'Catalog'       => [P::COLLECT_PAYMENTS, P::MANAGE_BILLING],
        'Client'        => [P::MANAGE_CLIENTS, P::MANAGE_CLIENTS],
        'Consultation'  => [P::VIEW_MEDICAL_RECORD, P::CREATE_CONSULTATION],
        'Dashboard'     => [P::VIEW_DASHBOARD, P::VIEW_DASHBOARD],
        'Directory'     => [P::MANAGE_CLIENTS, P::MANAGE_CLIENTS],
        'Document'      => [P::VIEW_MEDICAL_RECORD, P::VIEW_MEDICAL_RECORD],
        'Notification'  => [P::VIEW_DASHBOARD, P::VIEW_DASHBOARD],
        'PortalRequest' => [P::VIEW_DASHBOARD, P::VIEW_DASHBOARD],
        'Prescription'  => [P::VIEW_MEDICAL_RECORD, P::CREATE_PRESCRIPTION],
        'Reporting'     => [P::MANAGE_BILLING, P::MANAGE_BILLING],
        // The agenda is open to every member; acting on it is not.
        'Scheduling' => [P::VIEW_DASHBOARD, P::MANAGE_APPOINTMENTS],
        'Search'     => [P::MANAGE_CLIENTS, P::MANAGE_CLIENTS],
        'Settings'   => [P::VIEW_DASHBOARD, P::VIEW_DASHBOARD],
        'Stock'      => [P::MANAGE_STOCK, P::MANAGE_STOCK],
    ];

    /** Whether the controller (`Class` or `Class::method`) is one of the clinic application's. */
    public function covers(string $controller): bool
    {
        return str_starts_with($controller, self::NAMESPACE);
    }

    /**
     * @throws \LogicException for a controller of an area that has no rule
     *
     * @return string|null the permission, null when the route needs none
     */
    public function permissionFor(string $controller, string $method): ?string
    {
        $class = explode('::', $controller, 2)[0];

        if (class_exists($class)) {
            foreach ((new \ReflectionClass($class))->getAttributes(RequiresClinicPermission::class) as $attribute) {
                return $attribute->newInstance()->permission;
            }
        }

        $area = explode('\\', substr($class, \strlen(self::NAMESPACE)), 2)[0];

        if (self::PUBLIC_AREA === $area) {
            return null;
        }

        if (!isset(self::AREAS[$area])) {
            throw new \LogicException(\sprintf('The clinic area "%s" of %s has no permission rule.', $area, $class));
        }

        return \in_array($method, ['GET', 'HEAD'], true) ? self::AREAS[$area][0] : self::AREAS[$area][1];
    }
}
