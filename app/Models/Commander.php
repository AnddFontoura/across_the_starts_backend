<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commander extends Model
{
    /** Roman numerals for display (index 1..10). */
    public const ROMAN = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X'];

    /** The four combat attributes, in canonical order. */
    public const ATTRIBUTES = ['pontaria', 'desvio', 'critico', 'velocidade'];

    /** Highest level a commander may reach (attributes stop growing here). */
    public const LEVEL_MAX = 50;

    /**
     * Experience required to advance FROM a given level to the next. The curve
     * is a simple quadratic: EXP_PER_LEVEL_BASE * level^2. Centralised here so
     * battle rewards and levelling stay balanced in one place.
     */
    public const EXP_PER_LEVEL_BASE = 100;

    /** Growth factor bounds and the cap on their sum for a single commander. */
    public const GROWTH_FACTOR_MIN = 0.0;
    public const GROWTH_FACTOR_MAX = 10.0;
    public const GROWTH_FACTOR_SUM_MAX = 20.0;

    protected $fillable = [
        'user_id',
        'commander_definition_id',
        'name',
        'rank',
        'level',
        'experience',
        'growth_pontaria',
        'growth_desvio',
        'growth_critico',
        'growth_velocidade',
        'prof_cruiser',
        'prof_battleship',
        'prof_frigate',
        'prof_fighter',
        'prof_machinegun',
        'prof_laser',
        'prof_missile',
    ];

    protected $casts = [
        'rank' => 'integer',
        'level' => 'integer',
        'experience' => 'integer',
        'growth_pontaria' => 'float',
        'growth_desvio' => 'float',
        'growth_critico' => 'float',
        'growth_velocidade' => 'float',
        'prof_cruiser' => 'integer',
        'prof_battleship' => 'integer',
        'prof_frigate' => 'integer',
        'prof_fighter' => 'integer',
        'prof_machinegun' => 'integer',
        'prof_laser' => 'integer',
        'prof_missile' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CommanderDefinition::class, 'commander_definition_id');
    }

    /** Proficiency level (1..5) for a ship class key. */
    public function classProficiency(string $class): int
    {
        return (int) ($this->{'prof_'.$class} ?? 1);
    }

    /** Proficiency level (1..5) for a weapon attack_type. */
    public function weaponProficiency(string $attackType): int
    {
        return (int) ($this->{'prof_'.$attackType} ?? 1);
    }

    /** The growth factor (0..10) rolled for a given attribute. */
    public function growthFactor(string $attribute): float
    {
        return (float) ($this->{'growth_'.$attribute} ?? 0.0);
    }

    /**
     * The effective value of an attribute at the commander's current level:
     *     base + (level - 1) * (natural_growth_per_level + growth_factor)
     * The level used is capped at LEVEL_MAX so attributes stop growing past 50.
     */
    public function attributeValue(string $attribute): float
    {
        if (! in_array($attribute, self::ATTRIBUTES, true)) {
            return 0.0;
        }

        $definition = $this->definition;
        $base = (float) ($definition?->{'base_'.$attribute} ?? 0);
        $natural = (float) ($definition?->natural_growth_per_level ?? 1);

        $level = min((int) $this->level, self::LEVEL_MAX);
        $growthPerLevel = $natural + $this->growthFactor($attribute);

        return $base + max(0, $level - 1) * $growthPerLevel;
    }

    /**
     * All four effective attribute values keyed by name.
     *
     * @return array<string, float>
     */
    public function attributes(): array
    {
        $out = [];
        foreach (self::ATTRIBUTES as $attr) {
            $out[$attr] = $this->attributeValue($attr);
        }

        return $out;
    }

    /**
     * Experience needed to go from `$level` to `$level + 1`.
     * Returns 0 once the commander is at (or past) LEVEL_MAX.
     */
    public static function expToNext(int $level): int
    {
        if ($level >= self::LEVEL_MAX) {
            return 0;
        }

        return self::EXP_PER_LEVEL_BASE * $level * $level;
    }

    /**
     * Grant experience to this commander, levelling up as thresholds are
     * crossed (capped at LEVEL_MAX, where surplus exp is discarded). Persists
     * the change. Returns how many levels were gained.
     */
    public function grantExperience(int $amount): int
    {
        $amount = max(0, $amount);
        if ($amount === 0) {
            return 0;
        }

        $gained = 0;
        $this->experience = (int) $this->experience + $amount;

        while ($this->level < self::LEVEL_MAX) {
            $needed = self::expToNext((int) $this->level);
            if ($needed <= 0 || $this->experience < $needed) {
                break;
            }
            $this->experience -= $needed;
            $this->level++;
            $gained++;
        }

        // At max level, stop accumulating surplus (attributes cap at 50).
        if ($this->level >= self::LEVEL_MAX) {
            $this->experience = 0;
        }

        $this->save();

        return $gained;
    }

    /**
     * Roll a set of growth factors for the four attributes: each in
     * [0, 10] (0.1 steps) with the four summing to at most 20.
     *
     * Each attribute is rolled independently and uniformly in [0, 10]. If the
     * four happen to sum to more than 20 the whole set is re-rolled (rejection
     * sampling). This keeps the distribution symmetric across attributes and
     * lets the sum land anywhere in [0, 20] rather than skewing high, so both
     * "0,0,10,10" and "5,5,5,5" style rolls are genuinely reachable.
     *
     * @return array<string, float> keyed by attribute name
     */
    public static function rollGrowthFactors(): array
    {
        $step = 0.1;
        $maxSteps = (int) round(self::GROWTH_FACTOR_MAX / $step);        // 100
        $budgetSteps = (int) round(self::GROWTH_FACTOR_SUM_MAX / $step); // 200

        do {
            $steps = [];
            foreach (self::ATTRIBUTES as $attr) {
                $steps[$attr] = random_int(0, $maxSteps);
            }
        } while (array_sum($steps) > $budgetSteps);

        $factors = [];
        foreach (self::ATTRIBUTES as $attr) {
            $factors[$attr] = round($steps[$attr] * $step, 2);
        }

        return $factors;
    }
}
