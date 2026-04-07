<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BloodType;
use App\Models\Rhesus;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
        ]);

        foreach (['A', 'B', 'AB', 'O'] as $type) {
            BloodType::create(['name' => $type]);
        }

        foreach (['+', '-'] as $r) {
            Rhesus::create(['type' => $r]);
        }
    }
}
