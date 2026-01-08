<?php

// app/Models/WorkingHour.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property mixed $is_closed
 * @property mixed $start_time
 * @property mixed $end_time
 * @property mixed $break_start_time
 * @property mixed $break_end_time
 */
class WorkingHour extends Model
{
    protected $table = 'working_hours';

    protected $fillable = [
        'weekday_id',
        'is_closed',
        'start_time',
        'end_time',
        'break_start_time',
        'break_end_time',
    ];

    protected $casts = [
        'is_closed' => 'boolean',
    ];

    public function weekday(): BelongsTo
    {
        return $this->belongsTo(Weekday::class);
    }
}

