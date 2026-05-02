<?php

declare(strict_types=1);

namespace App\Services\Order;

use Illuminate\Support\Facades\Log;

final class LogWithEventService
{
    public function execute(): void
    {
        Log::info('Order created', ['event' => 'order.created', 'orderId' => 1]);
    }
}
