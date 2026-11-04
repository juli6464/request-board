<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statuses', function (Blueprint $table) {
            $table->string('code', 50)->primary();
            $table->string('name', 100);
            $table->unsignedTinyInteger('sort_order');
            $table->boolean('is_final')->default(false);
        });

        // El catálogo es parte del esquema (sin él no pueden existir solicitudes),
        // por eso se inserta en la migración y no en un seeder opcional.
        DB::table('statuses')->insert([
            ['code' => 'created',     'name' => 'Creada',      'sort_order' => 1, 'is_final' => false],
            ['code' => 'in_review',   'name' => 'En revisión', 'sort_order' => 2, 'is_final' => false],
            ['code' => 'in_progress', 'name' => 'En progreso', 'sort_order' => 3, 'is_final' => false],
            ['code' => 'paused',      'name' => 'Pausada',     'sort_order' => 4, 'is_final' => false],
            ['code' => 'done',        'name' => 'Terminada',   'sort_order' => 5, 'is_final' => true],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
