<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Zone definitions
    |--------------------------------------------------------------------------
    |
    | Boundary rules apply differently per zone. Paths are relative to the
    | consumer project root. Zones are *additive tags* — a single file can
    | belong to multiple zones (e.g. `app/Repositories` is both `logic` and
    | `eloquent_allowed`). Validators and PHPStan rules read each zone
    | independently per their own rule, so there is no precedence to invent
    | at the validator layer.
    |
    */

    'zones' => [
        'logic' => [
            'app/Http/Controllers',
            'app/Services',
            'app/Repositories',
            'app/Queries',
            'app/Integrations',
            'app/Console/Commands',
            'app/Jobs',
        ],

        'framework_bridge' => [
            'app/Providers',
            'app/Http/Middleware',
        ],

        'eloquent_allowed' => [
            'app/Repositories',
            'app/Queries',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignore lists
    |--------------------------------------------------------------------------
    |
    | Validators skip files matching these patterns. The `arch:validate
    | --strict` flag bypasses both lists.
    |
    */

    'ignore' => [
        'paths' => [
            // 'app/Legacy/**',
        ],

        'classes' => [
            // 'App\Legacy\OldController',
        ],
    ],
];
