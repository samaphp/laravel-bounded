<?php

declare(strict_types=1);

namespace App\Services\Order;

use Illuminate\Auth\AuthManager;

final class ContractInjectingService
{
    public function __construct(private readonly AuthManager $auth)
    {
    }

    public function execute(): void
    {
        $this->auth->user();
    }
}
