<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * @property mixed $post
 */
class UpdatePostRequest extends BaseFormRequest
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

    /**
     * Determine if the user is authorized to make this request.
     */

    public function rules(): array
    {
        return [
            'lang'             => ['required', 'string', 'max:5'],
            'title'            => ['required', 'string', 'max:255'],
            'urlSlug'          => [
                'required',
                'string',
                'max:255',
                Rule::unique('posts', 'slug')->ignore($this->post->id),
            ],
            'previewText'      => ['required', 'string'],
            'content'          => ['required', 'string'],
            'featureImage'     => ['nullable', 'string'],
            'showAuthorName'   => ['nullable', 'boolean'],
            'status'           => ['required', 'in:Draft,Published,Archived'],
            'publishDate'      => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }
}
