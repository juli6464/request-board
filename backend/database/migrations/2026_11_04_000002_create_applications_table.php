<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('status', 50)->default('created');
            $table->timestamps();

            $table->foreign('status')->references('code')->on('statuses')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['status', 'created_at']);
        });

        Schema::create('application_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 50);
            $table->string('to_status', 50);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('from_status')->references('code')->on('statuses')->cascadeOnUpdate();
            $table->foreign('to_status')->references('code')->on('statuses')->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_status_logs');
        Schema::dropIfExists('applications');
    }
};
