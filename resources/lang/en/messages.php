<?php

return [
    'validation_failed' => 'Validation failed.',
    'forbidden' => 'Forbidden.',
    'resource_not_found' => 'Resource not found.',
    'endpoint_not_found' => 'Endpoint not found.',
    'something_went_wrong' => 'Something went wrong. Please try again later.',
    'server_error' => 'Server error.',
    'unauthorized' => 'Unauthorized.',
    'token_expired' => 'Your session has expired. Please log in again.',
    'token_invalid' => 'Invalid authentication token.',
    'token_missing' => 'Authentication token is missing.',
    'unauthenticated' => 'You must be logged in to access this resource.',
    'auth' => [
        'unauthorized' => 'You must be logged in to perform this action.',
    ],

        'booking' => [
            'only_bookings_can_be_cancelled' => 'Only bookings can be cancelled.',
            'completed_cannot_be_cancelled' => 'Completed bookings cannot be cancelled.',
            'cancel_only_own' => 'You can only cancel your own bookings.',
            'only_bookings_can_be_marked_paid' => 'Only bookings can be marked as paid.',
            'cancelled_cannot_be_marked_paid' => 'Cancelled bookings cannot be marked as paid.',
        ],
        'payment' => [
            'no_order' => 'Payment does not have an associated order.',
        ],
    ];
