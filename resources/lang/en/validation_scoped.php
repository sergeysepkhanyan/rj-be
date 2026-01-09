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

    'category' => [
        'name' => [
            'required' => 'Category name is required.',
            'string' => 'Category name must be text.',
            'max' => 'Category name cannot be longer than :max characters.',
            'unique' => 'This category name already exists for the selected gender.',
        ],
        'nameAr' => [
            'required' => 'Arabic category name is required.',
            'string' => 'Arabic category name must be text.',
            'max' => 'Arabic category name cannot be longer than :max characters.',
            'unique' => 'This Arabic category name already exists for the selected gender.',
        ],
    ],

    'payment_method' => [
        'type' => [
            'required' => 'Payment method type is required.',
            'string' => 'Payment method type must be text.',
            'in' => 'Payment method must be one of: :values.',
        ],
        'brand' => [
            'required' => 'Card brand is required.',
            'string' => 'Card brand must be text.',
        ],
        'provider' => [
            'required' => 'Payment provider is required.',
            'string' => 'Payment provider must be text.',
        ],
        'token' => [
            'required' => 'Payment token is required.',
            'string' => 'Payment token must be text.',
        ],
        'last4' => [
            'string' => 'Last 4 digits must be text.',
            'max' => 'Last 4 digits must be exactly :max characters.',
        ],
        'isDefault' => [
            'boolean' => 'Default payment flag must be true or false.',
        ],
        'meta' => [
            'array' => 'Meta data must be a valid object.',
        ],
    ],

    'product' => [
        'name' => [
            'required' => 'Product name is required.',
            'string' => 'Product name must be text.',
            'max' => 'Product name cannot exceed :max characters.',
        ],
        'nameAr' => [
            'required' => 'Arabic product name is required.',
            'string' => 'Arabic product name must be text.',
            'max' => 'Arabic product name cannot exceed :max characters.',
        ],
        'description' => [
            'required' => 'Product description is required.',
            'string' => 'Product description must be text.',
        ],
        'descriptionAr' => [
            'required' => 'Arabic description is required.',
            'string' => 'Arabic description must be text.',
        ],
        'price' => [
            'required' => 'Price is required.',
            'numeric' => 'Price must be a number.',
        ],
        'currency' => [
            'required' => 'Currency is required.',
            'string' => 'Currency must be text.',
            'max' => 'Currency cannot exceed :max characters.',
        ],
        'images' => [
            'required' => 'Please upload at least one image.',
            'array' => 'Images must be a list.',
            'min' => 'Please upload at least one image.',
        ],
        'images_item' => [
            'string' => 'Each image must be a valid string.',
        ],
        'discountType' => [
            'in' => 'Discount type must be one of: :values.',
        ],
        'status' => [
            'in' => 'Status must be one of: :values.',
        ],
        'details' => [
            'details' => [
                'required' => 'Detail title is required.',
            ],
            'detailsAr' => [
                'required' => 'Arabic detail title is required.',
            ],
        ],

        'removedFiles' => [
            'array' => 'Removed files must be a list.',
        ],
        'removedFiles_item' => [
            'string' => 'Each removed file must be a valid string.',
        ],

        'newFiles' => [
            'array' => 'New files must be a list.',
        ],
        'newFiles_item' => [
            'string' => 'Each new file must be a valid string.',
        ],
    ],


    'service' => [
        'name' => [
            'required' => 'Service name is required.',
            'string' => 'Service name must be text.',
            'max' => 'Service name cannot exceed :max characters.',
        ],
        'nameAr' => [
            'required' => 'Arabic service name is required.',
            'string' => 'Arabic service name must be text.',
            'max' => 'Arabic service name cannot exceed :max characters.',
        ],
        'description' => [
            'required' => 'Service description is required.',
            'string' => 'Service description must be text.',
        ],
        'descriptionAr' => [
            'required' => 'Arabic service description is required.',
            'string' => 'Arabic service description must be text.',
        ],
        'image' => [
            'required' => 'Image is required.',
            'string' => 'Image must be a valid string.',
        ],
        'categoryId' => [
            'required' => 'Category is required.',
            'integer' => 'Category must be a valid number.',
            'exists' => 'The selected category does not exist.',
        ],
    ],

    'staff' => [
        'role' => [
            'required' => 'Please select a staff role.',
            'in' => 'Role must be either Admin or Master.',
        ],

        'name' => [
            'required' => 'Full name is required.',
            'string' => 'Full name must be text.',
        ],

        'nameAr' => [
            'required_if' => 'Arabic name is required for masters.',
            'string' => 'Arabic name must be text.',
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

        'subservices' => [
            'array' => 'Subservices must be a list.',
            'exists' => 'One or more selected subservices are invalid.',
        ],

        'weekends' => [
            'array' => 'Weekends must be a list.',
            'exists' => 'One or more selected weekends are invalid.',
        ],
    ],

    'subservice' => [
        'serviceId' => [
            'required' => 'Please select a service.',
            'integer' => 'Service must be a valid number.',
            'exists' => 'The selected service does not exist.',
        ],

        'name' => [
            'required' => 'Subservice name is required.',
            'string' => 'Subservice name must be text.',
            'max' => 'Subservice name cannot exceed :max characters.',
            'unique' => 'This subservice name already exists under the selected service.',
        ],

        'nameAr' => [
            'required' => 'Arabic subservice name is required.',
            'string' => 'Arabic subservice name must be text.',
            'max' => 'Arabic subservice name cannot exceed :max characters.',
            'unique' => 'This Arabic subservice name already exists under the selected service.',
        ],

        'type' => [
            'required' => 'Please select a type.',
            'string' => 'Type must be text.',
            'in' => 'Type must be one of: :values.',
        ],

        'simple' => [
            'price' => [
                'required_if' => 'Price is required for Simple type.',
                'numeric' => 'Price must be a number.',
            ],
            'duration' => [
                'required_if' => 'Duration is required for Simple type.',
                'numeric' => 'Duration must be a number.',
            ],
            'currency' => [
                'required_if' => 'Currency is required for Simple type.',
                'string' => 'Currency must be text.',
            ],
            'durationUnit' => [
                'required_if' => 'Duration unit is required for Simple type.',
                'string' => 'Duration unit must be text.',
            ],
        ],

        'vatEnabled' => [
            'boolean' => 'VAT enabled must be true or false.',
        ],

        'items' => [
            'required_if' => 'Please add at least one variant item.',
            'array' => 'Items must be a list.',
        ],

        'items_fields' => [
            'name' => [
                'required_if' => 'Item name is required.',
                'string' => 'Item name must be text.',
                'max' => 'Item name cannot exceed :max characters.',
            ],
            'nameAr' => [
                'required_if' => 'Arabic item name is required.',
                'string' => 'Arabic item name must be text.',
                'max' => 'Arabic item name cannot exceed :max characters.',
            ],
            'price' => [
                'required_if' => 'Item price is required.',
                'numeric' => 'Item price must be a number.',
            ],
            'duration' => [
                'required_if' => 'Item duration is required.',
                'numeric' => 'Item duration must be a number.',
            ],
            'currency' => [
                'required_if' => 'Item currency is required.',
                'string' => 'Item currency must be text.',
            ],
            'durationUnit' => [
                'required_if' => 'Item duration unit is required.',
                'string' => 'Item duration unit must be text.',
            ],
            'vatEnabled' => [
                'boolean' => 'Item VAT enabled must be true or false.',
            ],
            'id' => [
                'integer' => 'Item id must be a valid number.',
                'exists' => 'The selected item does not exist.',
            ],

        ],
    ],

    'upload' => [
        'slug' => [
            'required' => 'Slug is required.',
            'string' => 'Slug must be text.',
        ],
        'image' => [
            'required' => 'Please upload an image.',
            'image' => 'The uploaded file must be an image.',
            'mimes' => 'Image format must be one of: :values.',
            'max' => 'Image size must not exceed :max KB.',
        ],

        'images' => [
            'required' => 'Please upload at least one file.',
            'array' => 'Images must be a list.',
        ],

        'images_item' => [
            'required' => 'Each file is required.',
            'file' => 'Each uploaded item must be a file.',
            'mimes' => 'Each file format must be one of: :values.',
            'max' => 'Each file size must not exceed :max KB.',
        ],
    ],

    'working_hours' => [
        'days' => [
            'required' => 'Days are required.',
            'array' => 'Days must be a list.',
            'min' => 'Please provide at least one day.',
        ],

        'day' => [
            'required' => 'Day is required.',
            'integer' => 'Day must be a number.',
            'between' => 'Day must be between 1 and 7.',
        ],

        'isClosed' => [
            'required' => 'Please specify if the day is closed.',
            'boolean' => 'Is closed must be true or false.',
        ],

        'startTime' => [
            'date_format' => 'Start time must be in the format HH:MM.',
        ],
        'endTime' => [
            'date_format' => 'End time must be in the format HH:MM.',
        ],
        'breakStartTime' => [
            'date_format' => 'Break start time must be in the format HH:MM.',
        ],
        'breakEndTime' => [
            'date_format' => 'Break end time must be in the format HH:MM.',
        ],

        'duplicate_days' => 'Duplicate day values are not allowed.',
        'start_end_required_when_open' => 'Start time and end time are required when the day is not closed.',
        'end_after_start' => 'End time must be after start time.',
        'break_both_required' => 'Please provide both break start time and break end time.',
        'break_end_after_start' => 'Break end time must be after break start time.',
        'break_within_hours' => 'Break time must be within working hours.',
    ],

];
