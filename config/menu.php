<?php

return [
    'manager_email' => env('MENU_MANAGER_EMAIL', 'sahaanik1045@gmail.com'),

    'default_roles' => [
        'admin',
        'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fixed Application Routes
    |--------------------------------------------------------------------------
    | Canonical navigation routes supported by the frontend. Menu names are
    | derived exclusively from this catalog so admins assign access without
    | renaming or breaking fixed routes.
    */
    'fixed_routes' => [
        [
            'path' => '/account',
            'title' => 'Accounts',
            'location' => 'Money',
            'icon' => 'CreditCard',
            'order' => 0,
        ],
        [
            'path' => '/categories',
            'title' => 'Categories',
            'location' => 'Money',
            'icon' => 'Tags',
            'order' => 1,
        ],
        [
            'path' => '/transactions',
            'title' => 'Transaction',
            'location' => 'Money',
            'icon' => 'CreditCard',
            'order' => 2,
        ],
        [
            'path' => '/transfers',
            'title' => 'Transfers',
            'location' => 'Money',
            'icon' => 'Shuffle',
            'order' => 3,
        ],
        [
            'path' => '/recurring-expenses',
            'title' => 'Recurring Expenses',
            'location' => 'Money',
            'icon' => 'Repeat',
            'order' => 4,
        ],
        [
            'path' => '/budgets',
            'title' => 'Budgets',
            'location' => 'Money',
            'icon' => 'WalletCards',
            'order' => 5,
        ],
        [
            'path' => '/blogs',
            'title' => 'Blogs',
            'location' => 'Portfolio',
            'icon' => 'BookOpen',
            'order' => 6,
        ],
        [
            'path' => '/publications',
            'title' => 'Publications',
            'location' => 'Portfolio',
            'icon' => 'GraduationCap',
            'order' => 7,
        ],
        [
            'path' => '/experiences',
            'title' => 'Experiences',
            'location' => 'Portfolio',
            'icon' => 'BriefcaseBusiness',
            'order' => 8,
        ],
        [
            'path' => '/projects',
            'title' => 'Projects',
            'location' => 'Portfolio',
            'icon' => 'Code2',
            'order' => 9,
        ],
        [
            'path' => '/testimonials',
            'title' => 'Testimonials',
            'location' => 'Portfolio',
            'icon' => 'Quote',
            'order' => 10,
        ],
        [
            'path' => '/trainings',
            'title' => 'Trainings',
            'location' => 'Portfolio',
            'icon' => 'Award',
            'order' => 11,
        ],
        [
            'path' => '/services',
            'title' => 'Services',
            'location' => 'Portfolio',
            'icon' => 'Layers',
            'order' => 12,
        ],
        [
            'path' => '/file-manager',
            'title' => 'File Manager',
            'location' => 'Tools',
            'icon' => 'Folder',
            'order' => 13,
        ],
        [
            'path' => '/financial-intelligence',
            'title' => 'Financial Intelligence',
            'location' => 'Tools',
            'icon' => 'BrainCircuit',
            'order' => 14,
        ],
        [
            'path' => '/report-delivery',
            'title' => 'Report Delivery',
            'location' => 'Tools',
            'icon' => 'BellRing',
            'order' => 15,
        ],
        [
            'path' => '/report-delivery/run-jobs',
            'title' => 'Run Report Jobs',
            'location' => 'Tools',
            'icon' => 'Play',
            'order' => 16,
        ],
        [
            'path' => '/reports',
            'title' => 'Reports',
            'location' => 'Tools',
            'icon' => 'BarChart3',
            'order' => 17,
        ],
        [
            'path' => '/menu-items',
            'title' => 'Menu Items',
            'location' => 'Tools',
            'icon' => 'ListTree',
            'order' => 18,
        ],
    ],
];
