<?php

declare(strict_types=1);

namespace App\Services\Order;

use Illuminate\Support\Facades\Bus;

final class BusChainVariableService
{
    public function execute(): void
    {
        $jobs = [new \stdClass()];
        Bus::chain($jobs)->dispatch();
    }
}
