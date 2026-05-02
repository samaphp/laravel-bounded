<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

final class CreateOrderRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        // Inside a FormRequest the bounded zone is framework_bridge-adjacent
        // (app/Http/Requests is not in any logic zone), so Validator::make
        // here would not be flagged anyway. This file just documents that
        // the rule's scope intentionally leaves Form Requests alone.
        return [
            'name' => ['required', 'string'],
        ];
    }

    public function customValidator(): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($this->all(), $this->rules());
    }
}
