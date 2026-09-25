<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'is_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
            'is_admin' => 'boolean',
        ];
    }

    /**
     * The player's base (terrain). A player has one base per kind
     * (terrestrial / planetary); this returns whichever was created first.
     */
    public function base(): HasOne
    {
        return $this->hasOne(Base::class);
    }

    /**
     * All of the player's bases (terrestrial and planetary).
     */
    public function bases(): HasMany
    {
        return $this->hasMany(Base::class);
    }

    /**
     * The player's fleet: one row per aircraft type with a quantity.
     */
    public function aircraft(): HasMany
    {
        return $this->hasMany(Aircraft::class);
    }

    /**
     * Aircraft currently being built (across all hangar slots).
     */
    public function aircraftBuildOrders(): HasMany
    {
        return $this->hasMany(AircraftBuildOrder::class);
    }

    /**
     * The player's custom ship designs (blueprints).
     */
    public function shipDesigns(): HasMany
    {
        return $this->hasMany(ShipDesign::class);
    }

    /**
     * The player's commander pool (max 30).
     */
    public function commanders(): HasMany
    {
        return $this->hasMany(Commander::class);
    }

    /**
     * The player's fleets.
     */
    public function fleets(): HasMany
    {
        return $this->hasMany(Fleet::class);
    }

    /**
     * Pending commander recruitment (at most one at a time).
     */
    public function commanderRecruitments(): HasMany
    {
        return $this->hasMany(CommanderRecruitment::class);
    }
}
