<?php

namespace App\Http\Requests;

class UpdatePageSeoRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'metaTitle' => ['nullable', 'string', 'max:70'],
            'metaTitleAr' => ['nullable', 'string', 'max:70'],
            'metaDescription' => ['nullable', 'string', 'max:200'],
            'metaDescriptionAr' => ['nullable', 'string', 'max:200'],
            'keywords' => ['nullable', 'string'],
            'keywordsAr' => ['nullable', 'string'],
            'ogImage' => ['nullable', 'string', 'max:500'],
            'canonicalUrl' => ['nullable', 'string', 'max:500'],
        ];
    }
}
