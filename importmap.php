<?php

declare(strict_types=1);

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path'       => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '7.3.0',
    ],
    'kiveto/ui' => [
        'path' => './assets/js/ui/index.js',
    ],
    'kiveto/drawer' => [
        'path' => './assets/js/ui/drawer.js',
    ],
    'kiveto/modal' => [
        'path' => './assets/js/ui/modal.js',
    ],
    'kiveto/money' => [
        'path' => './assets/js/money.js',
    ],
    'kiveto/toast' => [
        'path' => './assets/js/ui/toast.js',
    ],
    'kiveto/popover' => [
        'path' => './assets/js/ui/popover.js',
    ],
    'kiveto/tabs' => [
        'path' => './assets/js/ui/tabs.js',
    ],
    'kiveto/shell' => [
        'path' => './assets/js/shell/shell.js',
    ],
    'kiveto/client-tax-profile' => [
        'path' => './assets/js/shared/client-tax-profile.js',
    ],
    'pages/home' => [
        'path' => './assets/js/pages/home.js',
    ],
    'pages/flux' => [
        'path' => './assets/js/pages/flux.js',
    ],
    'pages/scheduling/agenda' => [
        'path' => './assets/js/pages/scheduling/agenda.js',
    ],
    'pages/scheduling/planning' => [
        'path' => './assets/js/pages/scheduling/planning.js',
    ],
    'pages/clients/list' => [
        'path' => './assets/js/pages/clients/list.js',
    ],
    'pages/clients/create' => [
        'path' => './assets/js/pages/clients/create.js',
    ],
    'pages/animals/sheet' => [
        'path' => './assets/js/pages/animals/sheet.js',
    ],
    'pages/clients/sheet' => [
        'path' => './assets/js/pages/clients/sheet.js',
    ],
    'pages/dossier/kit' => [
        'path' => './assets/js/pages/dossier/kit.js',
    ],
    'pages/consultation' => [
        'path' => './assets/js/pages/consultation.js',
    ],
    'pages/consultations' => [
        'path' => './assets/js/pages/consultations.js',
    ],
    'pages/billing/register' => [
        'path' => './assets/js/pages/billing/register.js',
    ],
    'pages/prescription/register' => [
        'path' => './assets/js/pages/prescription/register.js',
    ],
    'pages/catalog' => [
        'path' => './assets/js/pages/catalog.js',
    ],
    'pages/team' => [
        'path' => './assets/js/pages/team.js',
    ],
    'pages/settings' => [
        'path' => './assets/js/pages/settings.js',
    ],
    'pages/stock' => [
        'path' => './assets/js/pages/stock.js',
    ],
    'pages/auth' => [
        'path' => './assets/js/pages/auth.js',
    ],
    'lucide' => [
        'version' => '1.7.0',
    ],
    'chart.js' => [
        'version' => '4.5.1',
    ],
    '@kurkle/color' => [
        'version' => '0.3.4',
    ],
];
