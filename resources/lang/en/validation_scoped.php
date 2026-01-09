<?php

return [
    'change_password' => [
        'oldPassword' => [
            'required' => 'Current password is required.',
            'string' => 'Current password must be a text.',
        ],
        'password' => [
            'required' => 'New password is required.',
            'string' => 'New password must be a text.',
            'min' => 'New password must be at least :min characters.',
            'same' => 'New password and confirmation must match.',
        ],
        'passwordConfirmation' => [
            'required' => 'Password confirmation is required.',
            'string' => 'Password confirmation must be a text.',
        ],
    ],

    'address' => [
        'store' => [
            'name' => [
                'required' => 'First name is required.',
                'string' => 'First name must be a text.',
                'max' => 'First name cannot be longer than :max characters.',
            ],
        ],
    ],
];
