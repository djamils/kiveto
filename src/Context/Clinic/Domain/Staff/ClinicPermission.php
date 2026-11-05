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

    /** The cash desk: check out a visit, record a payment, send and remind invoices. */
    case COLLECT_PAYMENTS = 'collect_payments';

    /** Weight and temperature of a consultation, without the rest of the record. */
    case RECORD_VITALS = 'record_vitals';

    /** Stock, lots and supplier orders. */
    case MANAGE_STOCK = 'manage_stock';
}
