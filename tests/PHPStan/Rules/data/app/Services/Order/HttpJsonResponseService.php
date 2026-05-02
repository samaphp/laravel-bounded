<?php

declare(strict_types=1);

namespace App\Services\Order;

use Illuminate\Http\JsonResponse;

final class HttpJsonResponseService
{
    public function execute(): JsonResponse
    {
        return new JsonResponse([]);
    }
}
