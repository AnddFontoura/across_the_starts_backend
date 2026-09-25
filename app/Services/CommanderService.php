<?php

namespace App\Services;

use App\Models\Commander;
use App\Models\CommanderDefinition;
use App\Models\CommanderRecruitment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Commander pool + hourly recruitment. Recruitment is unlocked once the player
 * has a built Aircraft Hangar (the ship factory). One recruitment runs at a
 * time and takes one hour, producing a generic "Comandante" with randomly
 * rolled proficiencies (I..V) and commander rank I.
 */
class CommanderService
{
    public const POOL_MAX = 30;
    public const RECRUIT_SECONDS = 3600; // 1 hour

    public function __construct(protected FleetService $fleet)
    {
    }

    /** Whether the player can recruit (has a built hangar). */
    public function canRecruit(User $user): bool
    {
        return $this->fleet->hangar($user) !== null;
    }

    /** Commanders currently in the pool. */
    public function poolCount(User $user): int
    {
        return (int) Commander::where('user_id', $user->id)->count();
    }

    /** The player's active recruitment, if any. */
    public function activeRecruitment(User $user): ?CommanderRecruitment
    {
        return CommanderRecruitment::where('user_id', $user->id)->first();
    }

    /**
     * Start a recruitment. Requires a hangar, no recruitment already running,
     * and room in the pool.
     *
     * @throws ValidationException
     */
    public function recruit(User $user): CommanderRecruitment
    {
        $this->settle($user);

        if (! $this->canRecruit($user)) {
            throw ValidationException::withMessages([
                'hangar' => ['Construa um Hangar de Aeronaves para recrutar comandantes.'],
            ]);
        }

        if ($this->activeRecruitment($user) !== null) {
            throw ValidationException::withMessages([
                'recruitment' => ['Já existe um recrutamento em andamento.'],
            ]);
        }

        if ($this->poolCount($user) >= self::POOL_MAX) {
            throw ValidationException::withMessages([
                'pool' => ['Limite de '.self::POOL_MAX.' comandantes atingido.'],
            ]);
        }

        $definition = CommanderDefinition::where('is_recruitable', true)->firstOrFail();

        return CommanderRecruitment::create([
            'user_id' => $user->id,
            'commander_definition_id' => $definition->id,
            'finishes_at' => now()->addSeconds(self::RECRUIT_SECONDS),
        ]);
    }

    /**
     * Finalize a completed recruitment into a commander with random
     * proficiencies. Respects the pool cap (if full when it completes, the
     * recruitment stays pending until there's room). Returns the created
     * commander or null.
     */
    public function settle(User $user, ?CarbonInterface $now = null): ?Commander
    {
        $now ??= now();

        $recruitment = CommanderRecruitment::where('user_id', $user->id)
            ->where('finishes_at', '<=', $now)
            ->first();

        if (! $recruitment) {
            return null;
        }

        // Don't exceed the pool cap; keep it pending until there's room.
        if ($this->poolCount($user) >= self::POOL_MAX) {
            return null;
        }

        return DB::transaction(function () use ($recruitment, $user) {
            $definition = $recruitment->definition;

            $commander = Commander::create([
                'user_id' => $user->id,
                'commander_definition_id' => $definition->id,
                'name' => $definition->name,
                // Simple commanders are always rank I; custom ones may differ.
                'rank' => 1,
                'prof_cruiser' => random_int(1, 5),
                'prof_battleship' => random_int(1, 5),
                'prof_frigate' => random_int(1, 5),
                'prof_fighter' => random_int(1, 5),
                'prof_machinegun' => random_int(1, 5),
                'prof_laser' => random_int(1, 5),
                'prof_missile' => random_int(1, 5),
            ]);

            $recruitment->delete();

            return $commander;
        });
    }

    /** Seconds remaining on the active recruitment (0 if none). */
    public function recruitmentRemaining(User $user, ?CarbonInterface $now = null): int
    {
        $now ??= now();
        $r = $this->activeRecruitment($user);
        if (! $r) {
            return 0;
        }

        return max(0, (int) ceil($now->diffInSeconds($r->finishes_at, false)));
    }
}
