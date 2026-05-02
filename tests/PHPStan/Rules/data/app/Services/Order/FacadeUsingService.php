<?php

declare(strict_types=1);

namespace App\Services\Order;

use Illuminate\Support\Facades\Auth;

final class FacadeUsingService
{
    public function execute(): void
    {
        Auth::user();
    }
}
