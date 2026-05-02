<?php

declare(strict_types=1);

namespace App\Services\Order;

final class CreateOrderRequest
{
    public string $name = '';
}

final class RequestSuffixDtoService
{
    public function execute(CreateOrderRequest $input): void
    {
        //
    }
}
