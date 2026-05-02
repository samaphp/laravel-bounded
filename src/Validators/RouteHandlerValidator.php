<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Validators;

use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;

/**
 * Forbids inline logic in `routes/*.php`.
 *
 * Routes must dispatch to invokable controller classes — never closures.
 * Closures in routes hide logic outside the controller layer, escape
 * Deptrac/PHPStan boundaries, and bypass Form Request validation.
 *
 * Allowed:
 *   - `Route::get('/orders/{id}', \App\Http\Controllers\Order\Show::class);`
 *   - `Route::redirect('/old', '/new');` (no logic)
 *   - `Route::view('/static', 'view-name');` (no logic)
 *   - `Route::permanentRedirect(...)` (no logic)
 *   - `Route::group(...)` / `Route::middleware(...)` / `Route::prefix(...)` etc. (containers, the inner Route::* calls are checked)
 *
 * Flagged:
 *   - `Route::get('/x', function () { ... });`
 *   - `Route::get('/x', fn () => view('welcome'));`
 *   - `Route::get('/x', [Controller::class, 'method']);` (multi-action — controller must be invokable)
 *   - any verb method (get/post/put/patch/delete/options/any/match/fallback) with a non-class-string handler
 */
final class RouteHandlerValidator implements ValidatorInterface
{
    private const VERB_METHODS = [
        'get', 'post', 'put', 'patch', 'delete', 'options',
        'any', 'match', 'fallback',
    ];

    private const NO_HANDLER_METHODS = [
        'redirect', 'permanentRedirect', 'view',
    ];

    public function __construct(
        private readonly string $basePath,
    ) {
    }

    public function name(): string
    {
        return 'RouteHandler';
    }

    public function validate(): ValidatorResult
    {
        $routesDir = rtrim($this->basePath, '/') . '/routes';
        if (! is_dir($routesDir)) {
            return new ValidatorResult(validator: $this->name());
        }

        $violations = [];

        foreach ($this->listRouteFiles($routesDir) as $file) {
            array_push($violations, ...$this->checkFile($file));
        }

        return new ValidatorResult(
            validator: $this->name(),
            violations: $violations,
        );
    }

    /**
     * @return list<string>
     */
    private function listRouteFiles(string $routesDir): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($routesDir, \FilesystemIterator::SKIP_DOTS),
        );

        $files = [];
        foreach ($iterator as $entry) {
            if (! $entry instanceof \SplFileInfo) {
                continue;
            }
            if ($entry->getExtension() !== 'php') {
                continue;
            }
            $files[] = $entry->getPathname();
        }

        return $files;
    }

    /**
     * @return list<Violation>
     */
    private function checkFile(string $absolutePath): array
    {
        $ast = PhpAstParser::parseFile($absolutePath);

        $printer = new PrettyPrinter();
        $relativeFile = $this->relativeFromBase($absolutePath);

        $violations = [];

        /** @var list<Node\Expr\StaticCall|Node\Expr\MethodCall> $calls */
        $calls = PhpAstParser::finder()->find($ast, static function (Node $node): bool {
            return $node instanceof Node\Expr\StaticCall || $node instanceof Node\Expr\MethodCall;
        });

        foreach ($calls as $call) {
            $methodName = $call->name instanceof Node\Identifier ? $call->name->name : null;
            if ($methodName === null) {
                continue;
            }

            if (! $this->isRouteCall($call)) {
                continue;
            }

            $lowerName = strtolower($methodName);

            if (in_array($lowerName, self::NO_HANDLER_METHODS, true)) {
                continue;
            }

            if (! in_array($lowerName, self::VERB_METHODS, true)) {
                continue;
            }

            $handlerArgIndex = $lowerName === 'match' ? 2 : 1;
            $args = $call->getArgs();
            if (! isset($args[$handlerArgIndex])) {
                continue;
            }

            $handler = $args[$handlerArgIndex]->value;

            if ($this->isAllowedHandler($handler)) {
                continue;
            }

            $violations[] = new Violation(
                file: $relativeFile,
                line: $call->getStartLine(),
                message: sprintf(
                    'Route handler must be an invokable controller class string. Found: `%s`. Use `\\App\\Http\\Controllers\\Domain\\Action::class` instead. No closures, no [Controller::class, \'method\'] arrays — controllers are single-action.',
                    $printer->prettyPrintExpr($handler),
                ),
            );
        }

        return $violations;
    }

    private function isRouteCall(Node\Expr\StaticCall|Node\Expr\MethodCall $call): bool
    {
        if ($call instanceof Node\Expr\StaticCall) {
            return $call->class instanceof Node\Name
                && in_array($call->class->toString(), ['Route', 'Illuminate\\Support\\Facades\\Route'], true);
        }

        // MethodCall: chained on a Route::middleware(...)->get(...) form.
        // Unwrap to find the root call and verify it's a Route facade.
        $cursor = $call->var;
        while ($cursor instanceof Node\Expr\MethodCall) {
            $cursor = $cursor->var;
        }

        return $cursor instanceof Node\Expr\StaticCall
            && $cursor->class instanceof Node\Name
            && in_array($cursor->class->toString(), ['Route', 'Illuminate\\Support\\Facades\\Route'], true);
    }

    private function isAllowedHandler(Node\Expr $handler): bool
    {
        // ::class fetch — Foo::class
        if ($handler instanceof Node\Expr\ClassConstFetch
            && $handler->name instanceof Node\Identifier
            && $handler->name->name === 'class'
        ) {
            return true;
        }

        // Plain string class name — must be a fully-qualified namespaced
        // identifier. Modern Laravel (8+) does not auto-namespace route
        // handlers, so a non-namespaced string handler ('Show') would fail
        // at boot anyway. Requiring '\' here makes the validator's promise
        // match what it actually checks.
        if ($handler instanceof Node\Scalar\String_) {
            return $this->looksLikeFqcn($handler->value);
        }

        return false;
    }

    private function looksLikeFqcn(string $value): bool
    {
        if (! str_contains($value, '\\')) {
            return false;
        }

        // Each segment must be a valid PHP identifier (no method-suffix
        // forms like 'App\Foo@method' — those are pre-Laravel-8 style
        // and explicitly removed).
        foreach (explode('\\', ltrim($value, '\\')) as $segment) {
            if ($segment === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function relativeFromBase(string $absolutePath): string
    {
        $prefix = rtrim($this->basePath, '/') . '/';
        if (! str_starts_with($absolutePath, $prefix)) {
            return $absolutePath;
        }

        return substr($absolutePath, strlen($prefix));
    }
}
