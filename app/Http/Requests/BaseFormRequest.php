<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

abstract class BaseFormRequest extends FormRequest
{
    protected array $fieldMap = [];

    /**
     * Automatically convert fields after validation
     */
    protected function passedValidation(): void
    {
        $mapped = [];
        foreach ($this->all() as $key => $value) {
            $newKey = $this->fieldMap[$key] ?? Str::snake($key);
            $mapped[$newKey] = $value;
        }

        $this->replace($mapped);
    }
}
