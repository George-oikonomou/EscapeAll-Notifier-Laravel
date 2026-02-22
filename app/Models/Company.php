<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    /** @use HasFactory<\\Database\\Factories\\CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'external_id',
        'name',
        'logo_url',
        'latitude',
        'longitude',
        'address',
        'full_address',
        'municipality_external_id',
    ];

    protected $casts = [
        'external_id' => 'string',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function hasRooms()
    {
        return $this->rooms()->exists();
    }
}
