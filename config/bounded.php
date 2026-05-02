<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Zone definitions
    |--------------------------------------------------------------------------
    |
    | Boundary rules apply differently per zone. Paths are relative to the
    | consumer project root.
    |
    | **Zones partition.** A path appears in exactly one zone — never two.
    | Validators and PHPStan rules pick which zones their rule applies to
    | and read those zones independently; they never have to resolve "what
    | if a file matches multiple zones?" because the partition makes that
    | case structurally impossible. The package fails at boot if any path
    | appears in more than one zone definition (see ZonePartitionValidator).
    |
    | The three structural zones are intentional:
    |   - `logic` — where business logic lives. Strictest rules: no facades,
    |     no HTTP types in return signatures, no `Request` in service
    |     method signatures.
    |   - `framework_bridge` — code whose purpose is framework boot or HTTP
    |     pipeline (providers, middleware). Facades permitted here because
    |     business logic does not live here.
    |   - `repository` — the only zone where Eloquent imports are permitted.
    |     Logic-style rules still apply (no facades, no HTTP returns); the
    |     Eloquent permission is the only difference from `logic`.
    |
    | **Mirror these zones in `src/PHPStan/Helpers/ZoneClassifier::DEFAULT_ZONES`.**
    | PHPStan runs without booting the Laravel app, so it can't read this
    | config file. The duplication is mechanically necessary; if you change
    | zones here, mirror them in ZoneClassifier (or the rules drift silently).
    |
    | **Future evolution.** If context-specific zones become needed
    | (per-bounded-context rules — Billing, Identity, etc.), the right
    | answer is composite zone definitions (`billing.logic`,
    | `identity.framework_bridge`) — not stacked tags on the existing
    | three. Don't reach for "deny-wins precedence" because the partition
    | feels constraining — the contract holds for structural zones; future
    | layers compose, they don't tag.
    |
    */

    'zones' => [
        'logic' => [
            'app/Http/Controllers',
            'app/Services',
            'app/Integrations',
            'app/Console/Commands',
            'app/Jobs',
        ],

        'framework_bridge' => [
            'app/Providers',
            'app/Http/Middleware',
        ],

        'repository' => [
            'app/Repositories',
            'app/Queries',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignore lists
    |--------------------------------------------------------------------------
    |
    | Validators skip scan paths listed here. The `arch:validate --strict`
    | flag bypasses the list. Use this to suppress validators for categories
    | the project doesn't use (e.g. `app/Jobs` for projects without queues).
    |
    */

    'ignore' => [
        'paths' => [
            // 'app/Jobs',
        ],
    ],
];
