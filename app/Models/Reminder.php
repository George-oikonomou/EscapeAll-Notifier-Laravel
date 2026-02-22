<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    public const TYPE_THIS_MONTH = 'this_month';
    public const TYPE_SPECIFIC_DAY = 'specific_day';
    public const TYPE_COMING_SOON = 'coming_soon';

    public const TYPES = [
        self::TYPE_THIS_MONTH,
        self::TYPE_SPECIFIC_DAY,
        self::TYPE_COMING_SOON,
    ];

    public const TYPE_LABELS = [
        self::TYPE_THIS_MONTH => 'Remind me this month',
        self::TYPE_SPECIFIC_DAY => 'Remind me on a specific day',
        self::TYPE_COMING_SOON => 'Notify when available',
    ];

    protected $table = 'reminders';

    protected $fillable = [
        'user_id',
        'room_id',
        'type',
        'remind_at',
        'notified',
        'last_notified_slots',
    ];

    protected $casts = [
        'remind_at' => 'date',
        'notified' => 'boolean',
        'last_notified_slots' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function isDue(): bool
    {
        if ($this->notified) {
            return false;
        }

        $today = now()->startOfDay();

        switch ($this->type) {
            case self::TYPE_THIS_MONTH:
                return true;
            case self::TYPE_SPECIFIC_DAY:
                return $this->remind_at && $this->remind_at->startOfDay()->lte($today);
            case self::TYPE_COMING_SOON:
                return $this->room && !$this->room->is_coming_soon;
            default:
                return false;
        }
    }
}
