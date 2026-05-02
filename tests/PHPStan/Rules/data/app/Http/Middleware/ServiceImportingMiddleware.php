<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Order\StringReturnService;
use Closure;

final class ServiceImportingMiddleware
{
    public function __construct(private readonly StringReturnService $service)
    {
    }

    public function handle($request, Closure $next)
    {
        return $next($request);
    }
}
