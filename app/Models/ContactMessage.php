<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $email
 * @property mixed $name
 */
class ContactMessage extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'message', 'ip', 'user_agent', 'emailed_at',
    ];
}
