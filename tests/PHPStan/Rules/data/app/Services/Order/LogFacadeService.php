<?php

declare(strict_types=1);

namespace App\Services\Order;

use Illuminate\Support\Facades\Log;

final class LogFacadeService
{
    public function execute(): void
    {
        Log::info('Order processed', ['event' => 'order.processed']);
    }
}
