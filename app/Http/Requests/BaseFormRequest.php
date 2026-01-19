<?php

namespace App\Http\Requests;

use App\Services\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

abstract class BaseFormRequest extends FormRequest
{
    protected array $fieldMap = [];

    public function authorize(): bool
    {
        return true;
    }

    public function attributes(): array
    {
        return trans('attributes');
    }

    protected function passedValidation(): void
    {
        $this->replace($this->mapKeysRecursively($this->all()));
    }

    private function mapKeysRecursively(array $data): array
    {
        $mapped = [];

        foreach ($data as $key => $value) {
            if (is_int($key)) {
                $newKey = $key;
            } else {
                $newKey = $this->fieldMap[$key] ?? Str::snake($key);
            }

            if (is_array($value)) {
                $mapped[$newKey] = $this->mapKeysRecursively($value);
            } else {
                $mapped[$newKey] = $value;
            }
        }

        return $mapped;
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            \App\Services\ApiResponse::error($validator->errors(), __('validation.failed'), 422)
        );
    }

}
