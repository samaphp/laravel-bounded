<?php

declare(strict_types=1);

namespace App\Services\Order;

final class InlineValidatorHelperService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): void
    {
        validator($data, [
            'name' => ['required', 'string'],
        ])->validate();
    }
}
