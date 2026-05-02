<?php

declare(strict_types=1);

namespace App\Services\Order;

use Illuminate\Support\Facades\Log;

final class LogWithoutEventService
{
    public function execute(): void
    {
        Log::info('Order created', ['orderId' => 1]);
    }
}
