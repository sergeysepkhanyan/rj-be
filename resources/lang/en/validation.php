<?php

return [
    'failed' => 'Validation failed.',

    'custom' => [
        'email' => [
            'required' => 'Email address is required.',
            'email' => 'Please enter a valid email address.',
            'unique' => 'This email address is already in use.',
        ],

        'mobile' => [
            'required' => 'Mobile number is required.',
            'unique' => 'This mobile number is already in use.',
        ],

        'password' => [
            'required' => 'Password is required.',
            'min' => 'Password must be at least :min characters.',
            'same' => 'Password and password confirmation must match.',
        ],

        'passwordConfirmation' => [
            'required' => 'Password confirmation is required.',
        ],

        'date' => [
            'required' => 'Please select a date.',
            'date_format' => 'Date must be in this format: :format.',
        ],

        'subServiceId' => [
            'integer' => 'Sub service is invalid.',
            'exists' => 'Selected sub service was not found.',
            'required_without' => 'Please select either a Sub service or a Sub service item.',
        ],

        'subServiceItemId' => [
            'integer' => 'Sub service item is invalid.',
            'exists' => 'Selected sub service item was not found.',
            'required_without' => 'Please select either a Sub service or a Sub service item.',
        ],

        'masterId' => [
            'required' => 'Please select a master.',
            'integer' => 'Master is invalid.',
            'exists' => 'Selected master was not found.',
        ],

        'reason' => [
            'string' => 'Cancellation reason must be a text.',
            'max' => 'Cancellation reason cannot be longer than :max characters.',
        ],

        'title' => [
            'required' => 'Title is required.',
            'string' => 'Title must be a text.',
        ],

        'urlSlug' => [
            'required' => 'URL slug is required.',
            'string' => 'URL slug must be a text.',
            'max' => 'URL slug cannot be longer than :max characters.',
            'regex' => 'URL slug can contain only lowercase letters, numbers, and hyphens.',
            'unique' => 'This URL slug is already in use.',
        ],

        'previewText' => [
            'required' => 'Preview text is required.',
            'string' => 'Preview text must be a text.',
        ],

        'content' => [
            'required' => 'Content is required.',
            'string' => 'Content must be a text.',
        ],

        'featureImage' => [
            'string' => 'Feature image must be a valid text (URL/path).',
        ],

        'publishDate' => [
            'date' => 'Publish date must be a valid date.',
            'before_or_equal' => 'Publish date cannot be in the future.',
        ],

        'showAuthorName' => [
            'boolean' => 'Show author name must be true or false.',
        ],

        'status' => [
            'required' => 'Status is required.',
            'in' => 'Status must be one of: :values.',
        ],

        'authorName' => [
            'string' => 'Author name must be a text.',
            'max' => 'Author name cannot be longer than :max characters.',
        ],
    ],
];
