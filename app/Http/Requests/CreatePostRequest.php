<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CreatePostRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'urlSlug'          => 'slug',
        'previewText'      => 'preview',
        'featureImage'     => 'image',
        'showAuthorName'   => 'show_author',
        'publishDate'      => 'publish_date',
        'metaTitle'        => 'meta_title',
        'metaDescription'  => 'meta_description',
        'authorName'       => 'author',
    ];

    public function rules(): array
    {
        return [
            'title'           => ['required', 'string'],
            'urlSlug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('posts', 'slug')->whereNull('deleted_at'),
            ],
            'previewText'     => ['required', 'string'],
            'content'         => ['required', 'string'],
            'featureImage'    => ['nullable', 'string'],
            'publishDate'     => ['nullable', 'date', 'before_or_equal:today'],
            'showAuthorName'  => ['nullable', 'boolean'],
            'status'          => ['required', 'in:Draft,Published,Archived'],
            'authorName'      => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('authorName')) {
            $this->merge([
                'authorName' => auth()->user()->name,
            ]);
        }
    }

}
