<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // El catálogo `statuses` lo inserta su migración; aquí solo datos de prueba.
        $this->call(ApplicationSeeder::class);
    }
}
