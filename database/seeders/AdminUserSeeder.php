<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * A default administrator for the config panel.
     * Credentials: admin@ats.local / admin123
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@ats.local'],
            [
                'name' => 'Administrador',
                'password' => 'admin123', // hashed by the model cast
                'is_admin' => true,
            ]
        );
    }
}
