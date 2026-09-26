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
 * has a built Aircraft Hangar (the ship factory). Recruiting instantly adds a
 * generic "Comandante" (randomly rolled proficiencies I..V, commander rank I)
 * and starts a one-hour cooldown before the next one can be recruited.
 *
 * The CommanderRecruitment row is used as that cooldown marker: while its
 * finishes_at is in the future no new commander may be recruited. It is not a
 * pending commander (the commander is created immediately on recruit).
 */
class CommanderService
{
    public const POOL_MAX = 30;
    public const RECRUIT_SECONDS = 3600; // 1 hour cooldown between recruitments

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

    /** The player's active recruitment cooldown, if one is still running. */
    public function activeRecruitment(User $user): ?CommanderRecruitment
    {
        return CommanderRecruitment::where('user_id', $user->id)->first();
    }

    /**
     * Recruit a commander immediately, then start a one-hour cooldown before
     * the next one can be recruited. Requires a hangar, no active cooldown, and
     * room in the pool.
     *
     * @throws ValidationException
     */
    public function recruit(User $user): Commander
    {
        // Clear any expired cooldown so a finished timer doesn't block us.
        $this->settle($user);

        if (! $this->canRecruit($user)) {
            throw ValidationException::withMessages([
                'hangar' => ['Construa um Hangar de Aeronaves para recrutar comandantes.'],
            ]);
        }

        if ($this->activeRecruitment($user) !== null) {
            throw ValidationException::withMessages([
                'recruitment' => ['Aguarde o tempo de recrutamento para recrutar outro comandante.'],
            ]);
        }

        if ($this->poolCount($user) >= self::POOL_MAX) {
            throw ValidationException::withMessages([
                'pool' => ['Limite de '.self::POOL_MAX.' comandantes atingido.'],
            ]);
        }

        $definition = CommanderDefinition::where('is_recruitable', true)->firstOrFail();

        // Roll the growth factors that shape how this commander's attributes
        // evolve with level. The four factors sum to at most 20 (see model).
        $factors = Commander::rollGrowthFactors();

        return DB::transaction(function () use ($user, $definition, $factors) {
            $commander = Commander::create([
                'user_id' => $user->id,
                'commander_definition_id' => $definition->id,
                'name' => $definition->name,
                // Simple commanders are always rank I; custom ones may differ.
                'rank' => 1,
                'level' => 1,
                'growth_pontaria' => $factors['pontaria'],
                'growth_desvio' => $factors['desvio'],
                'growth_critico' => $factors['critico'],
                'growth_velocidade' => $factors['velocidade'],
                'prof_cruiser' => random_int(1, 5),
                'prof_battleship' => random_int(1, 5),
                'prof_frigate' => random_int(1, 5),
                'prof_fighter' => random_int(1, 5),
                'prof_machinegun' => random_int(1, 5),
                'prof_laser' => random_int(1, 5),
                'prof_missile' => random_int(1, 5),
            ]);

            // Start the cooldown that blocks the next recruitment.
            CommanderRecruitment::create([
                'user_id' => $user->id,
                'commander_definition_id' => $definition->id,
                'finishes_at' => now()->addSeconds(self::RECRUIT_SECONDS),
            ]);

            return $commander;
        });
    }

    /**
     * Re-roll a commander's growth factors (used by the "Pergaminho do
     * Caminho" item). The commander must belong to the given user. Returns the
     * refreshed commander with its new factors.
     *
     * @throws ValidationException
     */
    public function rerollGrowthFactors(User $user, Commander $commander): Commander
    {
        if ($commander->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'commander' => ['Este comandante não pertence a você.'],
            ]);
        }

        $factors = Commander::rollGrowthFactors();

        $commander->update([
            'growth_pontaria' => $factors['pontaria'],
            'growth_desvio' => $factors['desvio'],
            'growth_critico' => $factors['critico'],
            'growth_velocidade' => $factors['velocidade'],
        ]);

        return $commander->refresh();
    }

    /**
     * Clear an expired recruitment cooldown so the player can recruit again.
     * The commander itself was already created at recruit time, so this only
     * removes the finished cooldown marker.
     */
    public function settle(User $user, ?CarbonInterface $now = null): void
    {
        $now ??= now();

        CommanderRecruitment::where('user_id', $user->id)
            ->where('finishes_at', '<=', $now)
            ->delete();
    }

    /** Seconds remaining on the active recruitment cooldown (0 if none). */
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
