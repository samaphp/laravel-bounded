<?php

declare(strict_types=1);

namespace App\Services\Order;

use Illuminate\Support\Facades\Bus;

final class BusChainLiteralService
{
    public function execute(): void
    {
        Bus::chain([
            new \stdClass(),
            new \stdClass(),
        ])->dispatch();
    }
}
