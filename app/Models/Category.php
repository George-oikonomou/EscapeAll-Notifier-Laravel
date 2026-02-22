<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    /** @use HasFactory<\\Database\\Factories\\CategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'code',
        'icon',
        'emoji',
    ];

    protected $casts = [
        'code' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Accessor: translated name for the current locale via lang files.
     */
    public function getNameAttribute(): string
    {
        $key = $this->slug;
        $value = trans("categories.$key");

        return $value === "categories.$key" ? $this->slug : $value;
    }

    public function scopeSlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    public function scopeCode($query, int $code)
    {
        return $query->where('code', $code);
    }
}
