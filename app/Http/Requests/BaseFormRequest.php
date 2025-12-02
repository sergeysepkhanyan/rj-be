<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

abstract class BaseFormRequest extends FormRequest
{
    protected array $fieldMap = [];

    protected function passedValidation(): void
    {
        $this->replace($this->mapKeysRecursively($this->all()));
    }

    private function mapKeysRecursively(array $data): array
    {
        $mapped = [];

        foreach ($data as $key => $value) {

            $newKey = $this->fieldMap[$key] ?? Str::snake($key);

            if (is_array($value)) {
                $mapped[$newKey] = $this->mapKeysRecursively($value);
            } else {
                $mapped[$newKey] = $value;
            }
        }

        return $mapped;
    }
}
