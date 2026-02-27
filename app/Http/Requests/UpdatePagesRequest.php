<?php

namespace App\Http\Requests;

class UpdatePagesRequest extends BaseFormRequest
{
    protected array $fieldMap = [];

    public function rules(): array
    {
        $allKeys = ['homepage', 'reviews', 'footer', 'contact', 'about', 'blog', 'store', 'general'];

        $rules = [];
        foreach ($allKeys as $key) {
            $others = implode(',', array_filter($allKeys, fn($k) => $k !== $key));
            $rules[$key] = ["required_without_all:{$others}", 'array'];
        }

        return $rules;
    }
}
