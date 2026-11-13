<?php

/*
|--------------------------------------------------------------------------
| Staff roles → permissions
|--------------------------------------------------------------------------
|
| Least-privilege map used by User::hasPermission() and every Filament
| resource (see App\Filament\Concerns\ChecksPermissions). "admin" bypasses
| this list entirely. Abilities are "<area>.<view|manage>" plus a few
| sensitive standalone ones (orders.refund, customers.export, ...).
|
*/

return [

    'areas' => [
        'catalog' => 'Products, categories, brands, reviews',
        'inventory' => 'Stock levels and adjustments',
        'orders' => 'Orders, shipments, returns',
        'customers' => 'Customer records',
        'support' => 'Support tickets',
        'marketing' => 'Coupons, banners',
        'content' => 'CMS pages',
        'reports' => 'Dashboard & exports',
        'settings' => 'Store, shipping, payment configuration',
        'integrations' => 'Sales channels, API keys, webhooks',
        'staff' => 'Staff users and roles',
        'audit' => 'Audit log',
    ],

    'roles' => [
        'manager' => [
            'catalog.view', 'catalog.manage', 'inventory.view', 'inventory.manage',
            'orders.view', 'orders.manage', 'orders.refund', 'customers.view', 'customers.manage',
            'customers.export', 'marketing.view', 'marketing.manage', 'content.view', 'content.manage',
            'reports.view', 'settings.view', 'audit.view', 'integrations.view', 'support.view', 'support.manage',
        ],
        'catalog_manager' => [
            'catalog.view', 'catalog.manage', 'inventory.view', 'inventory.manage', 'content.view', 'reports.view',
        ],
        'fulfillment' => [
            'orders.view', 'orders.manage', 'inventory.view', 'inventory.manage', 'catalog.view', 'support.view',
        ],
        'support' => [
            'orders.view', 'orders.manage', 'customers.view', 'customers.manage', 'catalog.view', 'content.view',
            'support.view', 'support.manage',
        ],
        'marketing' => [
            'marketing.view', 'marketing.manage', 'content.view', 'content.manage', 'catalog.view',
            'reports.view', 'customers.view',
        ],
        'finance' => [
            'orders.view', 'orders.refund', 'reports.view', 'customers.view', 'customers.export', 'settings.view',
        ],
        'analyst' => [
            'catalog.view', 'inventory.view', 'orders.view', 'customers.view', 'marketing.view',
            'content.view', 'reports.view', 'support.view',
        ],
        'customer' => [],
    ],

];
