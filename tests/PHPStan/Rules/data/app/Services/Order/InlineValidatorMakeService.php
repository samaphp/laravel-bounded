<?php

declare(strict_types=1);

namespace App\Services\Order;

use Illuminate\Support\Facades\Validator;

final class InlineValidatorMakeService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): void
    {
        Validator::make($data, [
            'name' => ['required', 'string'],
        ])->validate();
    }
}
