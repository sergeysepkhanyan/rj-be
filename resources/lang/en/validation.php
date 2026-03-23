<?php

return [
    'failed' => 'Validation failed.',
    'insufficient_stock' => 'Insufficient stock. Available: :available.',
    'product_not_found' => 'Product not found (ID: :id).',
    'cart' => [
        'guest_session_required' => 'Guest session id is required.',
        'empty' => 'Cart is empty.',
        'customer_email_required' => 'Customer email is required.',
        'mixed_currency' => 'Cart items have different currencies.',
        'address_required' => 'Delivery address is required.',
        'address_id_required' => 'Address is required.',
        'payment_method_required' => 'Payment method is required.',
        'payment_method_invalid' => 'Payment method is invalid.',
        'saved_payment_method_requires_customer' => 'This saved card cannot be used. Please pay with a new card.',
        'item_product_unavailable' => 'One or more products in your cart are no longer available. Please refresh your cart and try again.',
        'checkout_integrity_failed' => 'Checkout could not be completed due to a data inconsistency. Please try again or contact support.',
    ],

    'order' => [
        'items_required' => 'At least one order line item is required.',
        'invalid_line_quantity' => 'Each line item must have a quantity of at least 1.',
        'subtotal_lines_mismatch' => 'Subtotal does not match the sum of line items (price × quantity).',
        'total_breakdown_mismatch' => 'Total does not match subtotal, tax, and discount.',
        'line_persistence_mismatch' => 'Order line items could not be saved correctly. The operation was rolled back.',
    ],

    'custom' => [

        'referral_id' => [
            'exists' => 'The selected referral is invalid.',
        ],

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
            'string' => 'Password must be a text.',
            'confirmed' => 'Password confirmation does not match.',
        ],
        'identifier' => [
            'required' => 'Email or phone is required.',
            'string' => 'Email or phone must be a text.',
        ],
        'token' => [
            'required' => 'Token is required.',
            'string' => 'Token must be a text.',
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

        'type' => [
            'required' => 'Address type is required.',
            'in' => 'Address type must be one of: :values.',
        ],

        'isDefault' => [
            'boolean' => 'Default address must be true or false.',
        ],

        'lastName' => [
            'string' => 'Last name must be a text.',
            'max' => 'Last name cannot be longer than :max characters.',
        ],

        'address' => [
            'required' => 'Address is required.',
            'string' => 'Address must be a text.',
            'max' => 'Address cannot be longer than :max characters.',
        ],

        'additionalAddress' => [
            'string' => 'Additional address must be a text.',
            'max' => 'Additional address cannot be longer than :max characters.',
        ],

        'city' => [
            'required' => 'City is required.',
            'string' => 'City must be a text.',
            'max' => 'City cannot be longer than :max characters.',
        ],

        'state' => [
            'required' => 'State is required.',
            'string' => 'State must be a text.',
            'max' => 'State cannot be longer than :max characters.',
        ],

        'zipCode' => [
            'required' => 'ZIP code is required.',
            'string' => 'ZIP code must be a text.',
            'max' => 'ZIP code cannot be longer than :max characters.',
        ],

        'setDefaultShipping' => [
            'boolean' => 'Set as default shipping address must be true or false.',
        ],

        'setDefaultBilling' => [
            'boolean' => 'Set as default billing address must be true or false.',
        ],

        'startTime' => [
            'required' => 'Start time is required.',
            'date_format' => 'Start time must be in this format: :format.',
        ],
        'endTime' => [
            'required' => 'End time is required.',
            'date_format' => 'End time must be in this format: :format.',
        ],
        'timezone' => [
            'string' => 'Timezone must be a text.',
            'max' => 'Timezone cannot be longer than :max characters.',
        ],
        'customerName' => [
            'string' => 'Customer name must be a text.',
            'max' => 'Customer name cannot be longer than :max characters.',
        ],
        'customerPhone' => [
            'string' => 'Customer phone must be a text.',
            'max' => 'Customer phone cannot be longer than :max characters.',
        ],
        'customerEmail' => [
            'required' => 'Customer email is required.',
            'email' => 'Please enter a valid email address.',
        ],
        'paymentMode' => [
            'required' => 'Payment method is required.',
            'in' => 'Payment method must be one of: :values.',
        ],
        'services' => [
            'required' => 'Please select at least one service.',
            'array' => 'Services must be a list.',
            'min' => 'Please select at least one service.',
        ],
        'services.*.serviceType' => [
            'required' => 'Service type is required.',
            'in' => 'Service type must be one of: :values.',
        ],
        'services.*.serviceId' => [
            'required' => 'Service is required.',
            'integer' => 'Service is invalid.',
        ],
        'services.*.anyMaster' => [
            'boolean' => 'Any master must be true or false.',
        ],
        'services.*.masterId' => [
            'integer' => 'Master is invalid.',
            'exists' => 'Selected master was not found.',
        ],
        'services.*.price' => [
            'required' => 'Price is required.',
            'numeric' => 'Price must be a number.',
            'min' => 'Price must be at least :min.',
        ],
        'discountType' => [
            'in' => 'Discount type must be one of: :values.',
        ],
        'discountValue' => [
            'numeric' => 'Discount value must be a number.',
            'min' => 'Discount value must be at least :min.',
        ],
        'discountLabel' => [
            'string' => 'Discount label must be a text.',
            'max' => 'Discount label cannot be longer than :max characters.',
        ],
        'notes' => [
            'string' => 'Notes must be a text.',
            'max' => 'Notes cannot be longer than :max characters.',
        ],

        'phone' => [
            'string' => 'Phone number must be text.',
            'max' => 'Phone number cannot exceed :max characters.',
        ],
        'message' => [
            'required' => 'Message is required.',
            'string' => 'Message must be text.',
            'min' => 'Message must be at least :min characters.',
            'max' => 'Message cannot exceed :max characters.',
        ],

        'gender' => [
            'required' => 'Please select a gender.',
            'in' => 'Gender must be one of: Male, Female, or Kids.',
        ],

        'image' => [
            'required' => 'Image is required.',
        ],

    ],

    'auth' => [
        'invalid_verification_link' => 'This verification link is invalid or has expired.',
        'expired_verification_link' => 'This verification link has expired. Please request a new one.',
        'email_already_verified' => 'This email address has already been verified.',
    ],

    'break' => [
        'end_after_start' => 'End time must be after start time.',
        'master_unavailable' => 'This master is not available for the selected time.',
    ],

    'available_slots' => [
        'master_required' => 'Please select a master.',
        'date_required' => 'Please select a date.',
    ],

    'booking' => [
        'services_required' => 'Please select at least one service.',
        'service_type_and_id_required' => 'Please choose a service type and a service.',
        'service_invalid_for_type' => 'The selected service does not match the chosen service type.',
        'master_required_when_any_false' => 'Please choose a master for this service.',
        'master_forbidden_when_any_true' => 'You do not need to choose a master for this service.',
        'master_not_found' => 'The selected master could not be found.',
        'user_not_master' => 'The selected person is not available as a master.',
        'service_start_required' => 'Please select a start time for this service.',
        'service_end_required' => 'Please select an end time for this service.',

        'end_after_start' => 'End time must be after start time.',
        'invalid_duration_config' => 'This service has an invalid duration configuration.',
        'duration_mismatch' => 'This service should take :expected minutes, but the selected time is :actual minutes.',

        'unknown_service_type' => 'Unknown service type.',
        'invalid_time_format' => 'Invalid time format: :time.',

        'root_start_must_match' => 'The booking start time must match the first service start time (:time).',
        'root_end_must_match' => 'The booking end time must match the last service end time (:time).',

        'closed_day' => 'We are closed on the selected day.',
        'start_must_be_future' => 'Start time must be in the future.',
        'within_working_hours' => 'Please choose a time within working hours (:start–:end).',
        'time_grid_5min' => 'Please choose times in 5-minute steps.',
        'overlaps_break' => 'Your booking overlaps the break time (:start–:end).',

        'master_cannot_perform_service' => 'The selected master is not available for this service.',
        'no_masters_for_service' => 'No masters are available for this service.',
        'no_available_master' => 'No master is available for the selected time.',
        'master_unavailable' => 'This master is not available for the selected time.',
        'master_day_off' => 'Selected master is not available on this day.',
        'slot_already_selected' => 'This time slot is already selected.',
        'guest_session_required' => 'Guest session is required for booking selection.',
        'same_service_already_selected' => 'You cannot select the same service twice at the same time.',
        'same_service_same_time_not_allowed' => 'The same service cannot be booked twice in overlapping time slots.',
        'master_overlap_same_timeslot' => 'The same master cannot be assigned to overlapping time slots, even for different services.',
        'service_already_booked_at_time' => 'This service is already booked at the selected time. Please choose a different time.',
        'master_already_booked_at_time' => 'This master is already booked at the selected time. Please choose a different time or master.',
    ],

    'contact' => [
        'bot_detected' => 'Bot detected.',
    ],

    'profile' => [
        'name' => [
            'required' => 'Name is required.',
            'string' => 'Name must be text.',
            'max' => 'Name cannot exceed :max characters.',
        ],
        'email' => [
            'required' => 'Email address is required.',
            'email' => 'Please enter a valid email address.',
            'unique' => 'This email is already in use.',
        ],
        'mobile' => [
            'required' => 'Mobile number is required.',
            'string' => 'Mobile number must be text.',
            'unique' => 'This mobile number is already in use.',
        ],
        'dateOfBirth' => [
            'required' => 'Date of birth is required.',
            'date' => 'Date of birth must be a valid date.',
            'date_format' => 'Date of birth must be in the format YYYY-MM-DD.',
            'min_age' => 'You must be at least 18 years old.',
        ],
    ],

    'payment_method' => [
        'already_exists' => 'This payment method is already saved to your account.',
        'already_attached_to_another_customer' => 'This payment method is already attached to another account.',
        'cannot_be_reused' => 'This payment method was previously used or detached and cannot be reused. Please use a new payment method.',
    ],

];
