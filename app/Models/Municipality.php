<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Municipality extends Model
{
    /** @use HasFactory<\\Database\\Factories\\MunicipalityFactory> */
    use HasFactory;

    protected $fillable = [
        'external_id',
        'name',
    ];

    protected $casts = [
        'external_id' => 'string',
    ];
}

