<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommanderRecruitment extends Model
{
    protected $fillable = [
        'user_id',
        'commander_definition_id',
        'finishes_at',
    ];

    protected $casts = [
        'finishes_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CommanderDefinition::class, 'commander_definition_id');
    }
}
