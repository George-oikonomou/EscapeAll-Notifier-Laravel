<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favourite extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'favourites';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'room_id',
    ];

    /**
     * Get the user that owns the favourite.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the room that is favourited.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
