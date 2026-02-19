<?php

return [

    'staff' => [
        'admin_limit' => 'You can only have up to 2 admin users.',
        'marketer_limit' => 'You can only have up to 2 marketer users.',
        'cannot_delete_user_type' => 'You cannot delete this type of user.',
        'cannot_reset_superadmin_password' => 'You cannot reset the Super Admin password.',
    ],

    'common' => [
        'forbidden' => 'Forbidden.',
        'server_error' => 'Something went wrong. Please try again later.',
    ],
    'auth' => [
        'invalid_credentials' => 'Invalid email or password.',
        'email_not_verified' => 'Please verify your email first.',
        'temporary_password_already_used' => 'This temporary password has already been used. Please use your new password or reset your password.',
        'google_auth_failed' => 'Google authentication failed. Please try again.',
        'account_inactive' => 'Your account is not active. Please contact support.',
    ],
    'password' => [
        'reset_link_failed' => 'Reset link failed.',
        'reset_failed' => 'Password reset failed.',
        'user_not_found' => 'This email address is not registered in our system.',
        'invalid_token' => 'Invalid or expired token.',
        'temporary_incorrect' => 'Temporary password is incorrect.',
        'old_incorrect' => 'Old password is incorrect.',
        'update_failed' => 'Failed to update password.',
    ],


];
