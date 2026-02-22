<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAvailability extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'room_availabilities';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'room_id',
        'available_date',
        'available_time',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'available_date' => 'date',
        'available_time' => 'string',
    ];

    /**
     * Get the room that this availability belongs to.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}

