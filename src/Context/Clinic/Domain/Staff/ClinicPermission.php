<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Staff;

enum ClinicPermission: string
{
    case VIEW_DASHBOARD      = 'view_dashboard';
    case MANAGE_STAFF        = 'manage_staff';
    case MANAGE_BILLING      = 'manage_billing';
    case MANAGE_APPOINTMENTS = 'manage_appointments';
    case VIEW_MEDICAL_RECORD = 'view_medical_record';
    case CREATE_PRESCRIPTION = 'create_prescription';
    case CREATE_CONSULTATION = 'create_consultation';
    case MANAGE_CLIENTS      = 'manage_clients';
    case MANAGE_ANIMALS      = 'manage_animals';
}
