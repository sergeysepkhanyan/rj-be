<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
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

    protected function prepareForValidation(): void
    {
        // For partial updates (e.g. just `{status: 'Archived'}`), backfill
        // the omitted fields from the existing post so `required_if` sees
        // them and doesn't falsely report them missing. The merged values
        // are the ones already in the DB, so writing them back is a no-op.
        $post = $this->route('post');
        if (!$post) {
            return;
        }

        $backfill = [
            'previewText'     => $post->preview,
            'content'         => $post->content,
            'featureImage'    => $post->image,
            'publishDate'     => $post->publish_date
                ? (is_object($post->publish_date) ? $post->publish_date->format('Y-m-d') : $post->publish_date)
                : null,
            'metaTitle'       => $post->meta_title,
            'metaDescription' => $post->meta_description,
        ];

        $merge = [];
        foreach ($backfill as $key => $value) {
            // Only fill fields the client didn't send. If they explicitly
            // sent an empty value, respect that — it'll trigger required_if.
            if (!$this->has($key) && $value !== null && $value !== '') {
                $merge[$key] = $value;
            }
        }

        // Laravel's ConvertEmptyStringsToNull middleware turns "" into null
        // before we get here. The DB columns are NOT NULL, so any remaining
        // null on these string fields must be coerced back to "" to satisfy
        // the schema (drafts may legitimately have empty values).
        $stringFields = [
            'previewText',
            'content',
            'featureImage',
            'metaTitle',
            'metaDescription',
        ];
        foreach ($stringFields as $key) {
            $current = array_key_exists($key, $merge) ? $merge[$key] : $this->input($key);
            // Only act on null. Don't touch non-empty values or values the
            // user explicitly cleared (which we want to round-trip as '').
            if ($this->has($key) && $current === null) {
                $merge[$key] = '';
            }
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        // Title + slug are always required when present. Required-on-publish
        // fields are enforced only when the incoming status is Published /
        // Archived, so admins can keep editing drafts incrementally.
        return [
            'lang'             => ['sometimes', 'required', 'string', 'max:5'],
            'title'            => ['sometimes', 'required', 'string', 'max:255'],
            'urlSlug'          => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('posts', 'slug')->ignore($this->post->id),
            ],
            'previewText'      => ['required_if:status,Published,Archived', 'nullable', 'string'],
            'content'          => ['required_if:status,Published,Archived', 'nullable', 'string'],
            'featureImage'     => ['required_if:status,Published,Archived', 'nullable', 'string'],
            'publishDate'      => ['required_if:status,Published,Archived', 'nullable', 'date', 'before_or_equal:tomorrow'],
            'metaTitle'        => ['required_if:status,Published,Archived', 'nullable', 'string', 'max:70'],
            'metaDescription'  => ['required_if:status,Published,Archived', 'nullable', 'string', 'max:155'],
            'showAuthorName'   => ['nullable', 'boolean'],
            'status'           => ['sometimes', 'required', 'in:Draft,Published,Archived'],
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

    public function withValidator(Validator $validator): void
    {
        // Block "archive" on a draft — semantically wrong (drafts haven't
        // been published yet, so there's nothing to archive). Admins should
        // delete unwanted drafts. Also surfaces as a single, clear error
        // instead of a wall of "field required before publishing" messages.
        $validator->after(function (Validator $v) {
            $post = $this->route('post');
            $incomingStatus = $this->input('status');

            if ($post && $incomingStatus === 'Archived' && $post->status === 'Draft') {
                // Strip the misleading required_if errors that fired because
                // a draft is naturally missing those fields, and replace
                // them with one accurate, actionable message.
                $errors = $v->errors();
                foreach (['previewText', 'content', 'featureImage', 'publishDate', 'metaTitle', 'metaDescription'] as $field) {
                    $errors->forget($field);
                }
                $errors->add(
                    'status',
                    'Drafts cannot be archived. Publish the post first, or delete the draft if you no longer need it.'
                );
            }
        });
    }
}
