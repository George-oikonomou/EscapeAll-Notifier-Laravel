<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    /** @use HasFactory<\\Database\\Factories\\RoomFactory> */
    use HasFactory;

    // The table name will default to 'rooms' based on the class name.

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'external_id', // Unique identifier from the source system
        'label',
        'title',
        'provider',
        'slug',
        'short_description',
        'description',
        'rating',
        'reviews_count',
        'duration_minutes',
        'min_players',
        'max_players',
        'escape_rate',
        'image_url',
        'categories',
        'difficulty',
        'languages',
        'video_url',
        'municipality_id',
        'company_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'external_id' => 'string',
        'rating' => 'float',
        'reviews_count' => 'integer',
        'duration_minutes' => 'integer',
        'min_players' => 'integer',
        'max_players' => 'integer',
        'escape_rate' => 'float',
        'categories' => 'array',
        'difficulty' => 'float',
        'languages' => 'array',
        'municipality_id' => 'integer',
        'company_id' => 'integer',
    ];

    /**
     * Check if room is "coming soon" by looking at categories.
     */
    public function getIsComingSoonAttribute(): bool
    {
        $categories = $this->categories ?? [];
        if (is_string($categories)) {
            $categories = json_decode($categories, true) ?? [];
        }

        $comingSoonTerms = ['Σύντομα κοντά σας', 'Coming Soon', 'coming-soon'];
        foreach ($categories as $cat) {
            if (in_array($cat, $comingSoonTerms, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the municipality that owns the Room.
     */
    public function municipality()
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * Get the company that owns the Room.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the availabilities for the room.
     */
    public function availabilities()
    {
        return $this->hasMany(RoomAvailability::class);
    }

    /**
     * Get the users who favourited this room.
     */
    public function favouritedBy()
    {
        return $this->belongsToMany(User::class, 'favourites', 'room_id', 'user_id')->withTimestamps();
    }

    /**
     * Get the favourite records for this room.
     */
    public function favourites()
    {
        return $this->hasMany(Favourite::class);
    }

    /**
     * Get the duration in minutes with failsafe conversion.
     * If stored value is <= 5, treat it as hours and convert to minutes.
     */
    public function getDurationDisplayAttribute(): ?int
    {
        if ($this->duration_minutes === null) {
            return null;
        }

        // Failsafe: if duration is 5 or less, it's likely hours not minutes
        if ($this->duration_minutes > 0 && $this->duration_minutes <= 5) {
            return $this->duration_minutes * 60;
        }

        return $this->duration_minutes;
    }

    /**
     * Get formatted duration string (e.g., "60 min" or "1h 30min").
     */
    public function getFormattedDurationAttribute(): ?string
    {
        $minutes = $this->duration_display;

        if ($minutes === null) {
            return null;
        }

        if ($minutes >= 60) {
            $hours = floor($minutes / 60);
            $remainingMinutes = $minutes % 60;

            if ($remainingMinutes > 0) {
                return "{$hours}h {$remainingMinutes}min";
            }
            return "{$hours}h";
        }

        return "{$minutes} min";
    }
}
