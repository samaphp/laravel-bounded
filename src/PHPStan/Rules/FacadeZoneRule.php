<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Samaphp\LaravelBounded\PHPStan\Helpers\ZoneClassifier;

/**
 * Forbids Illuminate facades (Auth::, DB::, Cache::, etc.) inside logic
 * and repository zones. Facades are allowed only in framework_bridge
 * zones (`app/Providers`, `app/Http/Middleware`) where business logic
 * does not live.
 *
 * **One whitelisted exception: `Illuminate\Support\Facades\Log`.** Logging
 * is necessarily ambient — every service that does meaningful work needs
 * to log. Requiring constructor-injected `LoggerInterface` everywhere
 * would force every service to declare a logger dependency it barely uses,
 * for marginal gain. The carve-out is deliberate and the only one;
 * everything else (Auth::, DB::, Cache::, Queue::, etc.) goes through
 * constructor-injected contracts. The `LoggerEventKeyRule` separately
 * enforces that Log calls include an `event` key.
 *
 * @implements Rule<StaticCall>
 */
final class FacadeZoneRule implements Rule
{
    private const WHITELISTED_FACADES = [
        'Illuminate\\Support\\Facades\\Log',
    ];

    public function __construct(
        private readonly ZoneClassifier $zones,
    ) {
    }

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @param  StaticCall  $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        // Apply only to logic and repository zones; framework_bridge gets the carve-out.
        if (! $this->zones->isInAnyZone($file, ['logic', 'repository'])) {
            return [];
        }

        if (! $node->class instanceof Node\Name) {
            return [];
        }

        $className = $node->class->toString();
        if (! str_starts_with($className, 'Illuminate\\Support\\Facades\\')) {
            return [];
        }

        if (in_array($className, self::WHITELISTED_FACADES, true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Facade [%s] used in a logic zone. Facades are permitted only in app/Providers and app/Http/Middleware. Inject the underlying contract via constructor instead.',
                $className,
            ))->identifier('bounded.facadeZone')->build(),
        ];
    }
}
