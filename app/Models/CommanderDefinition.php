<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommanderDefinition extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'color',
        'image_url',
        'is_recruitable',
        'max_rank',
    ];

    protected $casts = [
        'is_recruitable' => 'boolean',
        'max_rank' => 'integer',
    ];

    public function commanders(): HasMany
    {
        return $this->hasMany(Commander::class);
    }
}
