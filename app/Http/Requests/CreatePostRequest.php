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
        // Drafts let admins save work-in-progress with only title + slug.
        // Required-on-publish fields are enforced only when status is
        // Published or Archived.
        return [
            'title'           => ['required', 'string'],
            'urlSlug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('posts', 'slug'),
            ],
            'previewText'     => ['required_if:status,Published,Archived', 'nullable', 'string'],
            'content'         => ['required_if:status,Published,Archived', 'nullable', 'string'],
            'featureImage'    => ['required_if:status,Published,Archived', 'nullable', 'string'],
            'publishDate'     => ['required_if:status,Published,Archived', 'nullable', 'date', 'before_or_equal:tomorrow'],
            'metaTitle'       => ['required_if:status,Published,Archived', 'nullable', 'string', 'max:70'],
            'metaDescription' => ['required_if:status,Published,Archived', 'nullable', 'string', 'max:155'],
            'showAuthorName'  => ['nullable', 'boolean'],
            'status'          => ['required', 'in:Draft,Published,Archived'],
            'authorName'      => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'previewText.required_if'     => 'Preview text is required before publishing',
            'content.required_if'         => 'Content is required before publishing',
            'featureImage.required_if'    => 'Feature image is required before publishing',
            'publishDate.required_if'     => 'Publish date is required before publishing',
            'metaTitle.required_if'       => 'Meta title is required before publishing',
            'metaDescription.required_if' => 'Meta description is required before publishing',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Default optional/required-on-publish fields to empty string so the
        // DB NOT NULL constraints accept drafts where the admin hasn't filled
        // these in yet. With status === Draft these aren't validated, but the
        // record still needs to satisfy the schema on insert.
        $stringDefaults = [
            'metaTitle',
            'metaDescription',
            'previewText',
            'content',
            'featureImage',
        ];
        $merge = [];
        foreach ($stringDefaults as $key) {
            if (!$this->filled($key)) {
                $merge[$key] = '';
            }
        }

        if (!$this->has('authorName')) {
            $merge['authorName'] = auth()->user()->name;
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

}
