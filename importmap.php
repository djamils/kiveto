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
    'kiveto/toast' => [
        'path' => './assets/js/ui/toast.js',
    ],
    'kiveto/popover' => [
        'path' => './assets/js/ui/popover.js',
    ],
    'kiveto/tabs' => [
        'path' => './assets/js/ui/tabs.js',
    ],
    'pages/home' => [
        'path' => './assets/js/pages/home.js',
    ],
    'pages/scheduling/agenda' => [
        'path' => './assets/js/pages/scheduling/agenda.js',
    ],
    'pages/scheduling/planning' => [
        'path' => './assets/js/pages/scheduling/planning.js',
    ],
    'pages/scheduling/waiting-room' => [
        'path' => './assets/js/pages/scheduling/waiting-room.js',
    ],
    'pages/hospitalisations' => [
        'path' => './assets/js/pages/hospitalisations.js',
    ],
    'pages/clients/list' => [
        'path' => './assets/js/pages/clients/list.js',
    ],
    'pages/clients/view' => [
        'path' => './assets/js/pages/clients/view.js',
    ],
    'pages/consultation' => [
        'path' => './assets/js/pages/consultation.js',
    ],
    'pages/select-clinic' => [
        'path' => './assets/js/pages/select-clinic.js',
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
