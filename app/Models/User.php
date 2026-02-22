<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's favourite rooms.
     */
    public function favourites()
    {
        return $this->hasMany(Favourite::class);
    }

    /**
     * Get the rooms that the user has favourited.
     */
    public function favouriteRooms()
    {
        return $this->belongsToMany(Room::class, 'favourites', 'user_id', 'room_id')->withTimestamps();
    }

    /**
     * Check if user has favourited a specific room.
     */
    public function hasFavourited(Room $room): bool
    {
        return $this->favourites()->where('room_id', $room->id)->exists();
    }

    /**
     * Get the user's reminders.
     */
    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }

    /**
     * Check if user has a reminder for a specific room.
     */
    public function hasReminder(Room $room): bool
    {
        return $this->reminders()->where('room_id', $room->id)->exists();
    }

    /**
     * Get the user's reminder for a specific room.
     */
    public function getReminderFor(Room $room): ?Reminder
    {
        return $this->reminders()->where('room_id', $room->id)->first();
    }
}
