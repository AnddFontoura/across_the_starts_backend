<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            StructureTypeSeeder::class,
            ResearchSeeder::class,
            AircraftTypeSeeder::class,
            ModuleTypeSeeder::class,
            CommanderSeeder::class,
            GameSettingSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
